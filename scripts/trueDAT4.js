/*===============================================================================
	trueDAT4.js
	John Larson
	10/09/11
	
	All page-specific JavaScript for trueDAT4.
	
	license: MIT-style
		
===============================================================================*/
	
	var App = {};
	App.thisPage = location.href.substring(location.href.lastIndexOf('/')+1).split('#')[0].split('?')[0];
	App.instanceKey = location.href.substring(0, location.href.lastIndexOf('trueDAT4')).split('://')[1];
	
window.addEvent('domready', function() {
	App.windowScroller = new Fx.Scroll(window);
	App.roar = new Roar({
		duration: 8000,
		position: 'upperRight',
		margin: {x: 30, y: 20}
	});
	dbug.enable();
});


	function initializeTrueDAT4() {
		App.tabManager = new TrueDATTabManager('tabHolder');
		App.SQLForm = $('SQLForm');
		App.SF = $('shortcutsForm');
		App.currentQueryState = [];
		App.SQLForm.SQL.suggest = new InlineSuggest(App.SQLForm.SQL, [], {
			exemptSet: ("SELECT FROM INNER OUTER LEFT RIGHT JOIN WHERE " +
				"NULL UPDATE INSERT DELETE LIMIT WHEN CASE THEN ELSE DESC COUNT " +
				"LTRIM RTRIM DISTINCT CONCAT IFNULL ISNULL GROUP ORDER HAVING ADD").split(' ')
		});
		
		App.databaseType = 'MySQL'; // unless overridden by loadDBStructure()
	//	App.SQLResizer = new TextareaSizer(App.SQLForm.SQL);
		HistoryManager.start();
		HistoryManager.setOptions({ iframeSrc: App.thisPage + '?a=blank' });
		
		loadPersistentState();
		window.addEvent('unload', savePersistentState);
		
		loadDBStructure(true);
		
		App.SF.table.addEvent('change', function() { App.persistentState.currentTable = this.value; });
		
		
		recallRecentQueries();
		
		loadFavoriteQueries();
		$('toolSelect').selectedIndex = 0;
		
		App.editPopUp = new PopUpWindow('Edit', {
			width: '264px', 
			contentDiv: $('tableEditForm')
		});
		
		App.columnPopUp = new PopUpWindow('Hide Columns', {
			width: 'auto', 
			contentDiv: $('columnHideForm'),
			onClose: function() { swapPrevious($('columnHideForm').getChildren()[1]) }
		});
		setHiddenColumnSelectOptions();
		
		prepForeignKeySurfing();
	}
	
	function loadPersistentState() {
		if(typeof(window.localStorage) == 'undefined') { App.persistentState = {}; return; }
		App.persistentState = $pick(JSON.decode(localStorage.getItem('trueDATState_' + App.instanceKey)), {});
	}
	
	function savePersistentState() {
		if(typeof(window.localStorage) == 'undefined') return;
		localStorage.setItem('trueDATState_' + App.instanceKey, JSON.encode(App.persistentState));
	}
	
	
/****************************************************************************
//	SECTION::Recent Queries
*/
	function recallRecentQueries() {
		var theSelect = $('recentQuerySelect');
		var SQLSet = $pick(App.persistentState.recentQuerySet, []);
		SQLSet.each(function(SQLQuery) {
			theSelect.options.add(new Option(SQLQuery, SQLQuery));
		});
		if(SQLSet.length > 0) App.SQLForm.SQL.value = SQLSet[0];
	}
	function addRecentQuery(SQLQuery) {
		if(SQLQuery.length > 500) return; // not a good fit for intended use of recent queries!
		var theSelect = $('recentQuerySelect');
		if(!theSelect.options[0]  ||  theSelect.options[0].value != SQLQuery) {
			// Remove duplicates if we have this one already:
			for(var i=1; i<theSelect.options.length; i++) {
				if(theSelect.options[i].value == SQLQuery)
					theSelect.remove(i);
			}
			theSelect.options.add(new Option(SQLQuery, SQLQuery), 0);
			theSelect.selectedIndex = 0;
			theSelect.options.length = Math.min(30, theSelect.options.length); // keep it tidy and manageable!
			App.persistentState.recentQuerySet = getSelectValueSet(theSelect);
		}
	}
	function getSelectValueSet(theSelect) {
		var result = [];
		for(var i=0; i<theSelect.options.length; i++) {
			result.push(theSelect.options[i].value );
		}
		return result;
	}
/*
//	End SECTION::Recent Queries
****************************************************************************/



/****************************************************************************
//	SECTION::Foreign Key Surfing
*/
	function prepForeignKeySurfing() {
		$('resultPanelHolder').addEvent('dblclick:relay(table.data td)', function() {
			foreignKeySurf(this);
		});
	}
	
	function foreignKeySurf(theTD) {
		if(!App.DBStructureData.foreignKeySet) return;
		
		// First figure out if theTD is in a column that is a foreign key:
		var queryDiv = theTD.getParent('div.queryResult');
		var theColumnIndex = theTD.getParent().getChildren().indexOf(theTD);
		var columnName = theTD.getParent('table').getFirst().getFirst().getChildren()[theColumnIndex].getProperty('text');
		var tableName = queryDiv.columnTableSet[theColumnIndex];
		var theFK = tableName + '.' + columnName;
		if(App.DBStructureData.foreignKeySet[theFK]) { // this indeed is a foreign key column
			var parentTable  = App.DBStructureData.foreignKeySet[theFK][0];
			var parentColumn = App.DBStructureData.foreignKeySet[theFK][1];
			FKSQL = 'SELECT * FROM ' + parentTable + ' WHERE ' + parentColumn + '=' + theTD.get('text');
			if(App.SQLForm.SQL.value.indexOf(FKSQL) == -1) {
				App.SQLForm.SQL.value += App.statementDelimiter + FKSQL;
				executeSQL();
			}
		}
	}
/*
//	End SECTION::Foreign Key Surfing
****************************************************************************/



/****************************************************************************
//	SECTION::User Login & Security
*/
	function loginUser(theForm) {
		showLoad();
		$('loginMessage').set('text', '');
		new Request.HTML({ url: App.thisPage + '?a=login',
			method: 'post',
			data: $('loginForm'),
			onComplete: function() {
				if(this.response.text == 'granted') {
					theForm.password.value = ''; // not appropriate to keep this around!
					if(App.reloggingIn) {
						$('loginMessage').set('text', 'Login successful!');
						hideLoad();
						swapSections('loginPage', 'mainPage');
					} else {
						$('loginMessage').set('text', 'Login successful!  Please wait while trueDAT loads.');
						new Request.HTML({ url: App.thisPage + '?a=loadApp',
							update: 'mainPage',
							onComplete: function() {
								hideLoad();
								swapSections('loginPage', 'mainPage');
							}
						}).send();
					}
				} else {
					hideLoad();
					$('loginMessage').set('html', this.response.text);
				}
			}
		}).send();
	}
	
	function logoutUser() {
		new Request.HTML({ url: App.thisPage,
			method: 'post',
			data: { a: 'logout' },
			onComplete: function() {
				$('loginMessage').set('text', 'You are now logged out.');
				swapSections('mainPage', 'loginPage');
			}
		}).send();
	}
/*
//	End SECTION::User Login & Security
****************************************************************************/



/****************************************************************************
//	SECTION::System Configuration
*/

	function manageDBInputState(theSelect) {
		if(theSelect.selectedIndex == 0) {
			$('DBInputTable').getElements('input[type=text]').each(function(x) { x.disabled = false });
		}
	}
	function loadAutoDetectDBSettings(theForm) {
		if(theForm.autodetect.selectedIndex > 0) {
			showLoad();
			new Request.JSON({ url: App.thisPage + '?a=loadAutoDetectDBSettings',
				method: 'post',
				data: { which: theForm.autodetect.value },
				onComplete: function(lookupResult) {
					hideLoad();
					if(lookupResult) {
						theForm.db_host.value		= lookupResult.host;
						theForm.db_username.value	= lookupResult.username;
						theForm.db_password.value	= lookupResult.password;
						theForm.db_schema.value		= lookupResult.schema;
						App.roar.alert('Settings found and loaded!');
						$('DBInputTable').getElements('input[type=text]').each(function(x) { x.disabled = true });
					} else {
						App.roar.alert('Sorry, could not find settings for ' + theForm.autodetect.value);
					}
				}
			}).send();
		}
	}
	function saveFirstConfiguration(theForm) {
		switch(theForm.step.value) {
		case '1':
			$('configStep1Result').setStyle('opacity', 0);
			showLoad();
			new Request.HTML({ url: App.thisPage + '?a=firstConfig1',
				method: 'post',
				data: theForm,
				update: 'configStep1Result',
				onComplete: function() {
					hideLoad();
					if(this.response.text == 'ok') {
						swapSections('configStep1', 'configStep2');
						theForm.step.value = '2';
					} else {
						$('configStep1Result').fade('in');
					}
				}
			}).send();
			break;
		case '2':
			if(!isRadioChoiceMade(theForm.authMode)) {
				alert('Please choose an authentication method.');
				return false;
			}
			if(theForm.authMode[0].checked) {
				if(!confirmNonEmpty(theForm.username, "Please enter the local user username.")) return false;
				if(!confirmNonEmpty(theForm.password, "Please enter the local user password.")) return false;
			}
			showLoad();
			new Request.HTML({ url: App.thisPage + '?a=firstConfig2',
				method: 'post',
				data: theForm,
				update: 'configStep2Result',
				onComplete: function() {
					hideLoad();
					theForm.baseURL.value = location.href.substring(0, location.href.lastIndexOf('/') + 1);
					window.theForm = theForm;
					if(this.response.text == 'ok') {
						swapSections('configStep2', 'configStep3');
						theForm.step.value = '3';
					} else {
						$('configStep2Result').fade('in');
					}
				}
			}).send();
			break;
		case '3':
			showLoad();
			new Request.HTML({ url: App.thisPage + '?a=firstConfig3',
				method: 'post',
				data: theForm,
				update: 'configStep4',
				onComplete: function() {
					hideLoad();
					swapSections('configStep3', 'configStep4');
				}
			}).send();
			break;
		}
	}
	function verifyConfigFileUpload() {
			showLoad();
			new Request.HTML({ url: App.thisPage + '?a=verifyConfigFileUpload',
				method: 'post',
				update: 'configStep4Result',
				onComplete: function() {
					hideLoad();
				}
			}).send();
	}

	function loadTrueDATConfigure() {
		new Request.HTML({ url: App.thisPage,
			method: 'post',
			data: { a: 'loadSystemConfig' },
			update: 'configurePage',
			onComplete: function() {
				swapSections('mainPage', 'configurePage');
			}
		}).send();
	}

/*
//	End SECTION::System Configuration
****************************************************************************/



var TrueDATTabManager = new Class({
	
	Implements: [Options, Events],
	Binds: ['addTab', 'swapInTab', 'swap', 'saveCurrentQueryState'],
	options: {
		initialTabCount:		2,
		maxTabCount:			7
	},
	
	initialize: function(options) {
		
		// Assume some structure about the TrueDAT UI:
		this.tabHolder				= $('tabHolder');
		this.SQLTextArea			= $('SQLTextArea');
		this.resultPanelHolder		= $('resultPanelHolder');
		
		this.currentTabIndex		= 0;
		this.executionHistoryPointer= 0;
		this.executionHistory		= [{ SQL: '', HTML: this.resultPanelHolder.getElement('div').get('html') }];
		this.tabHistoryPointers		= [0]; // index into executionHistory that each tab currently has loaded
		this.tabCurrentSQLSet		= [''];
		
		this.setOptions(options);
		var self = this;
		this.tabSwapper = new TabSwapper({
			tabs: $$("#tabHolder li.tab"),
			sections: $$("#resultPanelHolder div.resultPanel"),
			smooth: true,
			heightToAuto: true,
			manageHistory: true,
			historyKey: 'Tab',
			smoothSize: true,
			onActive: function(showingTabIndex) {
				self.tabCurrentSQLSet[self.currentTabIndex] = self.SQLTextArea.value;	// save current SQL to previous tab
				self.SQLTextArea.value = self.tabCurrentSQLSet[showingTabIndex];		// load new tab SQL
				self.currentTabIndex = showingTabIndex;
			}
		});
		
		var self = this;
		this.tabAdderButton = new Element('li', { 'id': 'tabAdderButton', 'text': 'Add tab' });
		this.tabAdderButton.addEvent('click', self.addTab);
		this.tabHolder.adopt(this.tabAdderButton);
		
		for(var i = 1; i < this.options.initialTabCount; i++) {
			this.addTab();
		}
		
		// Manage history of Query Executions:
		this.executionHistoryManager = HistoryManager.register(
			'Query',
			0,
			function(values) {
				if(this.executionHistoryPointer == parseInt(values[0]) || 0) return; // Query state has not changed, so do nothing
				this.executionHistoryPointer = Math.min(parseInt(values[0]) || 0, this.executionHistory.length-1);
				this.loadQueryState(this.executionHistory[this.executionHistoryPointer]);
			}.bind(this),
			function(values) {
				return 'Query(' + (parseInt(values[0])  || 0) + ')';
			}.bind(this),
			'Query\\((\\d+)\\)',
			{ skipDefaultMatch: true });
	},
	
	addTab: function() {
		var tabIndex = this.tabHolder.getChildren('.tab').length;
		var newTabLI = new Element('li', { 'text': 'Tab ' + (tabIndex + 1), 'class': 'tab' });
		this.tabHistoryPointers.push(0);
		
		newTabLI.injectBefore(this.tabAdderButton);
		var newResultBox = new Element('div', { 'class': 'resultPanel', 'html': '<h2 class="resultMessage">Tab ' + (tabIndex+1) + ' Result</h2>' });
		this.resultPanelHolder.adopt(newResultBox);
		this.tabSwapper.addTab(newTabLI, newResultBox, newTabLI);
		this.tabCurrentSQLSet.push('');
		if(tabIndex+1 == this.options.maxTabCount) { this.tabAdderButton.destroy(); }
	},
	
	saveCurrentQueryState: function() { // saves potentially volatile data to our history for recall later:
		if(!this.executionHistory[this.executionHistoryPointer]) return;  // no need to save, nothing is loaded yet!
		this.executionHistory[this.executionHistoryPointer].SQL = this.SQLTextArea.value;
	},
	
	getCurrentResultDiv: function() { return this.resultPanelHolder.getElements('div.resultPanel')[this.currentTabIndex]; },
	
	pushHistory: function(queryState) {
		this.executionHistoryPointer++;
		this.executionHistory[this.executionHistoryPointer] = queryState;
		this.executionHistory.length = this.executionHistoryPointer + 1; // truncate alternate future history, if applicable
		this.tabHistoryPointers[this.currentTabIndex] = this.executionHistoryPointer;
		this.executionHistoryManager.setValue(0, this.executionHistoryPointer);
	},
	
	loadQueryState: function(queryState) {
		this.SQLTextArea.value = queryState.SQL;
		this.getCurrentResultDiv().set('html', queryState.HTML);
	}
});


/****************************************************************************
//	SECTION::SQL Execution
*/
	function executeSQL() {
		var resultDiv = App.tabManager.getCurrentResultDiv();
		$(resultDiv).setStyle('opacity', 0);
		showLoad();
		App.editPopUp.close();
		App.columnPopUp.close();
		new Request.HTML({ url: App.thisPage + '?a=executeSQL',
			method: 'post',
			data: $('SQLForm'),
			update: resultDiv,
			evalScripts: true,
			onSuccess: function() {
				hideLoad();
				resultDiv.set('tween', {duration: 200}).tween('opacity', 0, 1);
				addRecentQuery($('SQLTextArea').value);
				
				// App.currentQueryState has been set by the evalScript, we'll augment it, and push it to history:
				App.currentQueryState['HTML'] = resultDiv.get('html');
				App.currentQueryState['SQL'] = App.SQLForm.SQL.value;
				App.tabManager.pushHistory(App.currentQueryState);
				
				// Make columns in each queryResult sortable, and show/hide Add & Edit buttons as appropriate:
				resultDiv.getElements('.queryResult').each(function(theDiv, i) {
					if(!App.currentQueryState.resultSet[i]) return; // this DIV might have errored out, so skip if no data
				//	theDiv.sorter = new TableSorter(theDiv.getElement('table'),
				//		{columnDataTypes: App.currentQueryState.resultSet[i].columnDataTypeSet });
					prepEditAndAddButtons(theDiv, App.currentQueryState.resultSet[i].SQL, App.currentQueryState.resultSet[i].columnDataTypeSet);
					theDiv.columnTableSet = App.currentQueryState.resultSet[i].columnTableSet; // save for later
				});
				
				hideCurrentTabColumns(); // as applicable for persistent state
			}
		}).send();
	}
	
	function makeColumnsSortable(theButton) {
		var theDiv = theButton.getParent('.queryResult');
		theButton.fade('out');
		theDiv.sorter = new TableSorter(theDiv.getElement('table'),
			{columnDataTypes: theDiv.columnDataTypeSet });
	}
	
	function exportToCSV() {
		$('SQLExportForm').SQL.value = App.SQLForm.SQL.value;
		prepFormForCSRFLegitimacy($('SQLExportForm'));
		$('SQLExportForm').submit();
	}

/*
//	End SECTION::SQL Execution
****************************************************************************/



/****************************************************************************
//	SECTION::Quick Query Action
*/
	
	function loadSchema() { if(App.SF.table.selectedIndex == 0) return;
		var schemaSQL;
		switch(App.databaseType) {
			case 'MSSQL': schemaSQL = 'sp_help';					break;
			case 'MySQL': schemaSQL = 'SHOW COLUMNS FROM';			break;
		}
		quickLoadSQL(schemaSQL + ' ' + App.SF.table.value);
	}
	function loadTriggers() { if(App.SF.table.selectedIndex == 0) return;
		var tableName = App.SF.table.value;
		switch(App.databaseType) {
			case 'MSSQL': quickLoadSQL('sp_helptrigger ' + tableName);					break;
			case 'MySQL': quickLoadSQL('SHOW TRIGGERS LIKE \'%' + tableName + '%\'');	break;
		}
	}
	function selectAll() { if(App.SF.table.selectedIndex == 0) return;
		quickLoadSQL(
			'SELECT * \n'  +
			'  FROM ' + App.SF.table.value);
	}
	function getCount() { if(App.SF.table.selectedIndex == 0) return;
		quickLoadSQL(
			'SELECT COUNT(*) AS recordCount \n' +
			'  FROM ' + App.SF.table.value);
	}
	function getTopRecords() { if(App.SF.table.selectedIndex == 0) return;
		var theCount = App.SF.topCount.value;
		var topClause = '', limitClause = '';
		switch(App.databaseType) {
			case 'MSSQL': topClause = 'TOP ' + theCount + ' ';		break;
			case 'MySQL': limitClause = '\n LIMIT ' + theCount;		break;
		}
		
		quickLoadSQL(
			'SELECT ' + topClause + '*\n' +
			'  FROM ' + App.SF.table.value + '\n' +
			' ORDER BY ' + App.DBStructureData.tablePrimaryKeySet[App.SF.table.value] + (App.SF.isDesc.checked ? ' DESC' : '') + limitClause);
	}
	function getIDEqualsRecord() { if(App.SF.table.selectedIndex == 0) return;
		quickLoadSQL(
			'SELECT *\n' +
			'  FROM ' + App.SF.table.value + '\n' +
			' WHERE ' + App.DBStructureData.tablePrimaryKeySet[App.SF.table.value] + '=' + App.SF.IDEquals.value);
	}
	function getStoredProcedureDefinition() { if(App.SF.storedProcedure.selectedIndex == 0) return;
		showLoad();
		new Request.HTML({ url: App.thisPage + '?a=getStoredProcedureDefinition',
			method: 'post',
			data: { SPName: App.SF.storedProcedure.value },
			onComplete: function() {
				hideLoad();
				// update our SQL with the stored procedure definition that comes back:
				App.SQLForm.SQL.value = this.response.text;
			}
		}).send();
	}
	
	function quickLoadSQL(SQL) { nearlyEqual($('SQLTextArea').value, SQL) ? executeSQL() : $('SQLTextArea').value = SQL; }
	function loadSelectQuery(theSelect) { $('SQLTextArea').value = $(theSelect).value; }
	
	function nearlyEqual(s1, s2) { // accounts for differences in linebreak structure, "\r\n" vs. just "\n"
		s1 = s1.replace(/\r\n/g, '\n');
		s2 = s2.replace(/\r\n/g, '\n');
		return s1 == s2;
	}
	
	function loadDBStructure(recallState) {
		if(recallState  &&  App.persistentState.DBStructureData) {
			App.DBStructureData = App.persistentState.DBStructureData; // quick recall
			integrateDBStructure();
		}
		else {
			showLoad();
			new Request.JSON({ url: App.thisPage + '?a=loadDBStructure',
				method: 'post',
				onComplete: function(DBStructureData) {
					hideLoad();
					App.DBStructureData = DBStructureData;
					App.persistentState.DBStructureData = App.DBStructureData; // save for later
					integrateDBStructure();
				}
			}).send();
		}
		function integrateDBStructure() {
			setSelectOptions(App.SF.table, ['', App.DBStructureData.tableSet].flatten(), [' - Select a table - ', App.DBStructureData.tableLabelSet].flatten());
			setSelectOptions(App.SF.storedProcedure, [' - Select a stored procedure - ', App.DBStructureData.SPSet].flatten());
			
			App.SF.table.value = App.persistentState.currentTable;
			
			setSelectOptions('tableTransferExportSelect', App.DBStructureData.tableSet);
			$('tableTransferExportSelect').size = App.DBStructureData.tableSet.length;
			
			App.SQLForm.SQL.suggest.setSuggestions(App.DBStructureData.suggestionSet);
			App.databaseType = App.DBStructureData.databaseType;
			App.statementDelimiter = App.DBStructureData.statementDelimiter;
			
			// Fix lower case foreign key tables: (http://bugs.mysql.com/bug.php?id=18446)
			if(App.DBStructureData.foreignKeySet  &&  typeOf(App.DBStructureData.foreignKeySet) == 'object') {
				for(var key in App.DBStructureData.foreignKeySet) {
					App.DBStructureData.foreignKeySet[key][0] = properTableCase(App.DBStructureData.foreignKeySet[key][0]);
				}
			}
		}
	}
	
	function loadSQLCheatSheet(cheatSheetData) {
		optionSet = cheatSheetData.split('\n====\n');
		cheatSelect = $('cheatSheetSelect');
		cheatSelect.options.length = 0;
		optionSet.each(function(option) {
			var label = option.substring(0, option.indexOf('\n'));
			var value = option.substring(option.indexOf('\n')+1);
			cheatSelect.options.add(new Option(label, value));
		});
	}

/*
//	End SECTION::Quick Query Action
****************************************************************************/



/****************************************************************************
//	SECTION::Favorite Queries
*/
	function loadFavoriteQueries() {
		var theDiv = $('favoriteQuerySet');
		theDiv.empty();
		var favoriteQuerySet = loadFavoriteQuerySet();
		favoriteQuerySet.each(function(favoriteQuery, index) {
			theDiv.adopt(buildFavoriteQueryButton(favoriteQuery[0], favoriteQuery[1]));
		});
	}
	function addFavoriteQuery(theButton) {
		var theInput = App.SQLForm.favoriteQueryName;
		if(theInput.value.trim() == '') return;
		var favoriteQuerySet = loadFavoriteQuerySet();
		favoriteQuerySet.push([theInput.value, App.SQLForm.SQL.value]);
		saveFavoriteQuerySet(favoriteQuerySet);
		$('favoriteQuerySet').adopt(buildFavoriteQueryButton(theInput.value, App.SQLForm.SQL.value));
		theInput.value = '';
	}
	function buildFavoriteQueryButton(name, SQL) {
		var button = new Element('div', { 'text': name, 'class': 'button' });
		button.SQL = SQL;
		var deleteButton = new Element('div', { 'class': 'delete' });
		deleteButton.addEvent('click', function(event) {
			(new Event(event)).stopPropagation();
			deleteFavoriteQuery(this);
		});
		button.adopt(deleteButton);
		button.addEvent('click', quickLoadSQL.pass(button.SQL));
		return button;
	}
	function deleteFavoriteQuery(deleteButton) {
		var theButton = deleteButton.getParent();
		var favoriteQuerySet = loadFavoriteQuerySet();
		for(i = 0; i < favoriteQuerySet.length; i++) {
			if(favoriteQuerySet[i][0] == theButton.get('text')  &&  favoriteQuerySet[i][1] == theButton.SQL) { // found it
				favoriteQuerySet.splice(i, 1);
				saveFavoriteQuerySet(favoriteQuerySet);
				loadFavoriteQueries();
				return;
			}
		}
	}
	
	function loadFavoriteQuerySet() {
		if(!window.localStorage) return [];
		var result = localStorage.getItem("trueDATFavoriteQueries") ? localStorage.getItem("trueDATFavoriteQueries").split('|@|') : [];
		for(var i = 0; i < result.length; i++) {
			result[i] = result[i].split('*$*');
		}
		return result;
	}
	function saveFavoriteQuerySet(favoriteQuerySet) {
		if(!window.localStorage) return;
		var saveData = [];
		for(var i = 0; i < favoriteQuerySet.length; i++) {
			saveData.push(favoriteQuerySet[i].join('*$*'));
		}
		localStorage.setItem("trueDATFavoriteQueries", saveData.join('|@|'));
	}
/*
//	End SECTION::Favorite Queries
****************************************************************************/


/****************************************************************************
//	SECTION::Hide Columns
*/
	function setHiddenColumnSelectOptions(hiddenColumnSet) {
		if(!hiddenColumnSet) { hiddenColumnSet = $pick(App.persistentState.hiddenColumnSet, []); }
		setSelectOptions($('columnHideForm').hiddenColumnSet, hiddenColumnSet);
		$('columnHideForm').hiddenColumnSet.size = Math.min(20, hiddenColumnSet.length)
	}
		
	function hideCurrentTabColumns() {
		var hiddenColumnSet = $pick(App.persistentState.hiddenColumnSet, []);
		App.tabManager.getCurrentResultDiv().getElements('.queryResult').each(function(queryDiv) {
			hideHiddenColumns(queryDiv, hiddenColumnSet);
		});
	}
	function hideHiddenColumns(queryDiv, hiddenColumnSet) {
		var theTable = queryDiv.getElement('table.data');
		var headerTDSet = theTable.getFirst().getFirst().getChildren();
		headerTDSet.each(function(headerTD, index) {
			var columnIsShowing = (headerTD.getStyle('display') != 'none');
			var columnShouldShow = !hiddenColumnSet.contains(queryDiv.columnTableSet[index] + '.' + headerTD.get('text'));
			if(columnShouldShow &&  !columnIsShowing) // need to newly show the column
				theTable.getElements('td:nth-child(' + (index+1) + ')').setStyle('display', '');
			else if(!columnShouldShow  &&  columnIsShowing) // need to newly hide the column
				theTable.getElements('td:nth-child(' + (index+1) + ')').setStyle('display', 'none');
			headerTD.setStyle('display', columnShouldShow ? '' : 'none');
		});
	}
	
	function loadShowHideColumnForm(theButton) {
		var queryDiv = theButton.getParent('div.queryResult');
		App.columnHideTargetQueryDiv = queryDiv;
		
		prepCurrentTableHideColumnSelect();
		
		App.columnPopUp.setPosition({relativeTo: theButton, 
			position: { x: 'center', y: 'top' },
			offset: {x: -150, y: -30 }
		});
		App.columnPopUp.open();
	}
	
	function prepCurrentTableHideColumnSelect() {
		var queryDiv = App.columnHideTargetQueryDiv;
		var theTable = queryDiv.getElement('table');
		var headerTDSet = theTable.getFirst().getFirst().getChildren();
		var columnSet = []; // array of [tableName, columnName, isHidden] elements
		headerTDSet.each(function(headerTD, index) {
			columnSet.push([queryDiv.columnTableSet[index], headerTD.get('text'), headerTD.getStyle('display') == 'none']);
		});
		
		var hiddenColumnSet = $pick(App.persistentState.hiddenColumnSet, []);
		
		var theSelect = $('columnHideForm').columnSet;
		theSelect.options.length = 0;
		columnSet.each(function(column, index) {
			var columnValue = column[0] + '.' + column[1];  // tableName.columnName
			theSelect.options.add(new Option(column[1], columnValue));
			if(hiddenColumnSet.contains(columnValue)  ||  column[2])
				theSelect.options[index].selected = true; // hidden per our persistent store OR current state
		});
		theSelect.size = columnSet.length;
	}
	
	function unhideTableColumns(theTable) {
		var headerTDSet = theTable.getFirst().getFirst().getChildren();
		headerTDSet.each(function(headerTD, index) {
			var columnIsShowing = (headerTD.getStyle('display') != 'none');
			if(headerTD.getStyle('display') == 'none') // need to newly show the column
				theTable.getElements('td:nth-child(' + (index+1) + ')').setStyle('display', '');
			headerTD.setStyle('display', '');
		});
	}
	
	function hideSelectedColumns(theForm) {
		var toHideColumnSet = getSelectSelectedValueSet(theForm.columnSet);
		if(theForm.persistShowHide.checked) {
			var hiddenColumnSet = ($pick(App.persistentState.hiddenColumnSet, [])).combine(toHideColumnSet);
			hiddenColumnSet.sort();
			setHiddenColumnSelectOptions(hiddenColumnSet);
			App.persistentState.hiddenColumnSet = hiddenColumnSet;
		}
		hideHiddenColumns(App.columnHideTargetQueryDiv, toHideColumnSet);
	}
	function removeHiddenColumns(theForm) {
		var removeColumnSet = getSelectSelectedValueSet(theForm.hiddenColumnSet);
		removeColumnSet.each(function(theColumn) {
			App.persistentState.hiddenColumnSet.erase(theColumn);
		});
		setHiddenColumnSelectOptions(App.persistentState.hiddenColumnSet);
		hideCurrentTabColumns();
		prepCurrentTableHideColumnSelect(); // to unselect any columns that were removed from our persistent state
	}
/*
//	End SECTION::Hide Columns
****************************************************************************/


/****************************************************************************
//	SECTION::Table Transfer
*/
	function beginTableTransferExport(theForm) {
		if(theForm.elements['tableSet[]'].value == '') return false;
		prepFormForCSRFLegitimacy(theForm);
		return true;
	}
	
	
	function beginTableTransferUpload(theForm) {
		if(theForm.theFile.value == '') return false;
		showLoad('Uploading...');
		prepFormForCSRFLegitimacy(theForm);
		var submitButton = theForm.getElement('.button');
		submitButton.disabled = true;
		submitButton.value = 'Uploading...';
		return true;
	}
	function completeTableTransferUpload(success) {
		hideLoad();
		var submitButton = $('tableTransferUploadForm').getElement('.button');
		submitButton.disabled = false;
		submitButton.value = 'Upload';
		if(success) {
			new Request.HTML({ url: App.thisPage + '?a=loadTableTransferState',
				method: 'post',
				update: 'uploadedTableTransferState'
			}).send();
		} else
			App.roar.alert('Upload Fail', 'Could not save your uploaded file.');
	}
	function deleteTableTransferFile() {
		new Request.HTML({ url: App.thisPage + '?a=deleteTableTransferFile',
			method: 'post',
			onComplete: function() {
				$('uploadedTableTransferState').set('html', '');
			}
		}).send();
	}
	
	
	function beginTableTransferImport(theForm) {
		if(theForm.elements['tableSet[]'].value == '') return false;
		$('tableTransferImportIFrame').setStyle('display', 'block');
		prepFormForCSRFLegitimacy(theForm);
		return true;
	}
	function completeTableTransferImport() {
		$('tableTransferImportIFrame').setStyle('display', 'none');
		dismissTool();
	}
/*
//	End SECTION::Table Transfer
****************************************************************************/



/****************************************************************************
//	SECTION::CSV Queries
*/
	function beginCSVUpload(theForm) {
		if(theForm.theFile.value == '') return false;
		showLoad('Uploading...');
		prepFormForCSRFLegitimacy(theForm);
		var submitButton = theForm.getElement('.button');
		submitButton.disabled = true;
		submitButton.value = 'Uploading...';
		return true;
	}
	
	function completeCSVUpload(success) {
		hideLoad();
		var submitButton = $('CSVUploadForm').getElement('.button');
		submitButton.disabled = false;
		submitButton.value = 'Upload';
		if(success) {
			new Request.HTML({ url: App.thisPage + '?a=loadCSVState',
				method: 'post',
				update: 'uploadedCSVState',
			}).send();
		} else
			App.roar.alert('Upload Fail', 'Could not save your uploaded file.');
	}
	
	function insertCSVField(fieldName) {
		$('CSVSQL').insertAtCursor('<$' + fieldName + '>', false);
	}
	function deleteCSVFile() {
		new Request.HTML({ url: App.thisPage + '?a=deleteCSVFile',
			method: 'post',
			onComplete: function() {
				$('uploadedCSVState').set('html', '');
			}
		}).send();
	}
	
	function beginCSVQuery(theForm) {
		if(theForm.CSVSQL.value == '') return false;
		$('CSVQueryIFrame').setStyle('display', 'block');
		prepFormForCSRFLegitimacy(theForm);
	}
	function completeCSVQuery() {
		$('CSVQueryIFrame').setStyle('display', 'none');
		dismissTool();
	}
/*
//	End SECTION::CSV Queries
****************************************************************************/




/****************************************************************************
//	SECTION::Value Finder
*/
	function beginValueFinder(theForm) {
		switch(theForm.which.value) {
			case 'string':	if(theForm.string.value == '')							return false; break;
			case 'number':	if(properNumber(theForm.number.value, 'nan') == 'nan')	return false; break;
			case 'date':	return true; break; // server side will say whether valid or not!
			default:		return false;
		}
		$('valueFinderIFrame').setStyle('display', 'block');
		prepFormForCSRFLegitimacy(theForm);
	}
	function completeValueFinder() {
		$('valueFinderIFrame').setStyle('display', 'none');
		dismissTool();
	}
	function selectValueFinderResults(VFSQL, addOn) {
		if(addOn) {
			if(App.SQLForm.SQL.value.indexOf(VFSQL) == -1)
				App.SQLForm.SQL.value += App.statementDelimiter + VFSQL;
			else
				quickLoadSQL(App.SQLForm.SQL.value);
		} else
			quickLoadSQL(VFSQL);
	}
	function replaceValueFinderResults(tableName, fieldList, whereClause, findValue, replaceValue) {
		updateSet = [];
		var fieldSet = fieldList.split(', ');
		fieldSet.each(function(field) {
			updateSet.push(field + '= REPLACE(' + field + ', ' + SQLValue(findValue) + ', ' + SQLValue(replaceValue) + ')');
		});
		var SQL = "UPDATE " + tableName + "\n" +
			"   SET " + updateSet.join(",\n       ") + "\n" +
			" WHERE " + whereClause;
		quickLoadSQL(SQL);
	}
	function SQLValue(value) { return "'" + value.replace("'", "''") + "'" }
/*
//	End SECTION::Value Finder
****************************************************************************/


/****************************************************************************
//	SECTION::Redcord Editing
*/
	function prepEditAndAddButtons(theDiv, SQL, columnDataTypeSet) {
		var theTable = theDiv.getElement('table');
		var tableName = extractSQLTableName(SQL);
		var IDName = App.DBStructureData.tablePrimaryKeySet[tableName];
		if(IDName) { // it's a known table, so far so good:
			// Pragmatic convention: the table ID MUST be the first column AND we expect it to be an integer:
			if(theTable.getFirst().getFirst().getFirst().get('text') == IDName  &&  columnDataTypeSet[0] == 'int') { // if so, Add/Edit is ok!
				theDiv.getElements('.edit').setStyle('display', 'inline-block');
				
				// theDiv will store helpful data by which we'll enable editing:
				theDiv.tableName = tableName;
				theDiv.columnDataTypeSet = columnDataTypeSet;
			}
		}
	}
	
	function toggleEditMode(theButton) {
		var dataTable = $(theButton).getParent('div.queryResult').getElement('table');
		if(dataTable.hasClass('editable')) { // leaving edit mode:
			dataTable.removeClass('editable');
			$(theButton).set('text', 'Edit');
			dataTable.removeEvents('click');
		}
		else { // entering edit mode:
			dataTable.addClass('editable');
			$(theButton).set('text', 'Exit Edit Mode');
			dataTable.addEvent('click', function(e) {
				if(e.target.tagName == 'TD')
					loadCellEditor($(e.target))
			});
		}
	}
	
	function loadCellEditor(theTD) {
		var theQueryDiv = theTD.getParent('div.queryResult');
		var theRow = theTD.getParent();
		var theRowTDs = theRow.getChildren();
		var rowID = properInt(theRowTDs[0].getProperty('text'), 0);
		if(rowID == 0) // no good
			return;
		
		var theTable = theQueryDiv.getElement('table');
		
		var theColumnIndex = theRowTDs.indexOf(theTD);
		var columnName = theTable.getFirst().getFirst().getChildren()[theColumnIndex].getProperty('text');
		
		// What table are we operating on?  Look to the SQL that gave it:
		var tabIndex = theTable.getParent().id.substring('resultPanel'.length);
		
		// Extract the table name that we seek:
		var tableName = theQueryDiv.tableName;
		if(theColumnIndex == 0) {
			if(confirm('Are you sure you want to delete from ' + tableName + ' where ' + columnName + '=' + rowID + '?')) {
				deleteTableRow(tableName, rowID, theRow);
			}
			return;
		}
		
		App.editPopUp.currentEditTD = theTD; // note this for AJAX update
		var dataType = theQueryDiv.columnDataTypeSet[theColumnIndex];
		
		var theEditForm = $('tableEditForm');
		theEditForm.tableName.value		= tableName;
		theEditForm.theID.value			= rowID;
		theEditForm.columnName.value	= columnName;
		theEditForm.dataType.value		= dataType;
		
		var currentValue = theTD.getProperty('text')  ||  '';
		if(dataType.toLowerCase() == 'boolean') { // no need to load the editor, we'll simply toggle:
			theEditForm.textbox.value = (currentValue=='True' ? '' : 'True');
			theEditForm.activeInput.value = 'textbox';
			updateTableField();
			return;
		}
		
		$('editorTextBoxControls').setStyle('display', 'none');
		$(theEditForm.textarea).setStyle('display', 'none');
		
		// Handle truncation or content altering by HTMLWhiteSpace: we'll sniff it out, and load the full value when we do.
		if(currentValue.substring(currentValue.length-3 == '...'  ||  App.SQLForm.showHTMLWhiteSpace.checked)) {
			showLoad();
			new Request.HTML({ url: App.thisPage + '?a=fetchTableField',
				method: 'post',
				async: false,
				data: theEditForm,
				onComplete: function() {
					hideLoad();
					currentValue = this.response.text;
				}
			}).send();
		}
		
		if(currentValue.indexOf('\n') == -1) { // no line breaks, so single line input
			$('editorTextBoxControls').setStyle('display', 'block');
			theEditForm.textbox.value = currentValue + '';
			theEditForm.activeInput.value = 'textbox';
		} else {
			$(theEditForm.textarea).setStyle('display', 'block');
			theEditForm.textarea.value = currentValue;
			theEditForm.activeInput.value = 'textarea';
		}
		
		App.editPopUp.setPosition({relativeTo: theTD, 
			position: { x: 'center', y: 'top' },
			offset: {x: -150, y: -30 }
		});
		App.editPopUp.setTitle('Edit ' + tableName + '.' + columnName + '<br />where ID=' + rowID);
		App.editPopUp.open();
	}
	
	function showCellEditorTextArea() {
		var theEditForm = $('tableEditForm');
		$('editorTextBoxControls').setStyle('display', 'none');
		$(theEditForm.textarea).setStyle('display', 'block');
		theEditForm.textarea.value = theEditForm.textbox.value;
		theEditForm.activeInput.value = 'textarea';
	}
	
	
	function updateTableField() {
		showLoad();
		new Request.HTML({ url: App.thisPage + '?a=updateTableField',
			method: 'post',
			data: $('tableEditForm'),
			update: App.editPopUp.currentEditTD,
			evalScripts: true,
			onComplete: function() {
				hideLoad();
				App.editPopUp.close();
			}
		}).send();
	}
	
	function deleteTableRow(tableName, theID, theTR) {
		showLoad();
		new Request.HTML({ url: App.thisPage + '?a=deleteTableRow',
			method: 'post',
			data: { tableName: tableName, theID: theID },
			evalScripts: true,
			onComplete: function() {
				hideLoad();
				if(this.response.text == 'ok')
					theTR.dispose();
				else
					App.roar.alert('Delete Failed', this.response.text, { duration: 500000 });
			}
		}).send();
	}
	
	function extractSQLTableName(SQL) {
		var tableSet = App.DBStructureData.tableSet;
		for(var i = 0; i < tableSet.length; i++) {
			var tableMatch = SQL.match(new RegExp("FROM\\s+(" + tableSet[i] + '\\b)', 'i'));
			if(tableMatch)
				return tableSet[i];
		}
		return '';
	}
	
	function properTableCase(tableName) {
		var TABLENAME = tableName.toUpperCase();
		var tableSet = App.DBStructureData.tableSet;
		for(var i = 0; i < tableSet.length; i++) {
			if(TABLENAME == tableSet[i].toUpperCase())
				return tableSet[i];
		}
		return tableName;
	}
/*
//	End SECTION::Redcord Editing
****************************************************************************/




/****************************************************************************
//	SECTION::Redcord Adding
*/
	function enterAddMode(addButton) {
		var queryDiv = $(addButton).getParent('div.queryResult')
		var theTable = queryDiv.getElement('table');
		$(addButton).dispose();
		var newTR = new Element('tr', { 'class': 'center addRow' });
		var tableName = queryDiv.tableName;
		
		var headerTDSet = theTable.getFirst().getFirst().getChildren();
		headerTDSet.each(function(headerTD, index) {
			var thisTD = new Element('td');
			if(index == 0) {
				thisTD.set('text', '...'); // the ID of the new row is, of course, TBD!
			}
			else {
				var thisDataType = queryDiv.columnDataTypeSet[index];
				var thisInput = new Element('input', { 'type': (thisDataType == 'boolean' ? 'checkbox' : 'text') });
				if(thisDataType == 'datetime')
					thisInput.value = (new Date()).format('%m/%d/%Y %H:%M:%S');  // default to right now while givine hint of format
				thisTD.adopt(thisInput);
			}
			newTR.adopt(thisTD);
		});
		unhideTableColumns(theTable);
		
		theTable.getFirst().adopt(newTR);
		queryDiv.getElement('.button.add').setStyle('display', '');
	}
	
	function addNewRow(addButton) {
	
		var queryDiv = $(addButton).getParent('div.queryResult')
		var theTable = queryDiv.getElement('table');
		var inputTR = theTable.getFirst().getLast();
		var newTDSet = inputTR.getChildren();
		
		var tableName = queryDiv.tableName;
		
		var headerTDSet = theTable.getFirst().getFirst().getChildren();
		var postData = { tableName: tableName };
		var columnNameSet = [];
		headerTDSet.each(function(headerTD, index) {
			if(index > 0) {
				var thisColumnName = headerTD.getProperty('text');
				var thisValue, thisInput = newTDSet[index].getFirst();
				if(thisInput.getAttribute('type') == 'checkbox')
					thisValue = thisInput.checked;
				else
					thisValue = thisInput.value;
				
				columnNameSet.push(thisColumnName);
				postData['newField' + index] = thisValue;
			}
		});
		
		// Now add on the list of column names, so we can spit 'em out in the right order:
		postData['columnNameList'] = columnNameSet.join(', ');
		postData['columnDataTypeList'] = queryDiv.columnDataTypeSet.join(', ');
		
		var insertTR = new Element('tr');
		insertTR.injectBefore(inputTR);
		
		showLoad();
		new Request.HTML({ url: App.thisPage + '?a=addTableRow',
			method: 'post',
			data: postData,
			evalScripts: true,
			update: insertTR,
			onComplete: hideLoad
		}).send();
	}
/*
//	End SECTION::Redcord Adding
****************************************************************************/




/****************************************************************************
//	SECTION::SQL Beautification
*/
	function beautifyTheSQL() { $('SQLTextArea').value = beautifySQL($('SQLTextArea').value); }
	function beautifySQL(SQL) {
		var result = SQL;
		result = result.replace(/\s*,\s*/g, ', '); // clean up spacing before/after commas
		result = result.replace(/\s+/g, ' ');  // collapse all whitespace to single spaces
		result = result.replace(/\s*([<>])?=\s*/g, ' $1= '); // tidy up spacing around equals signs
		
    	// Capitalize these keywords, and tighten up the spacing around them (" {keyword} "):
    	("SELECT TOP AS FROM INNER OUTER LEFT RIGHT JOIN ON WHERE AND OR IN IS NOT NULL UPDATE INSERT DELETE LIMIT " +
    	 "WHEN CASE IF THEN ELSE END BY ASC DESC").split(' ').each(function(theKeyword) {
    		result = result.replace(new RegExp("\\s*\\b" + theKeyword + "\\b\\s*", 'gi'), ' ' + theKeyword + " ");
    	});
    	
    	// Capitalize these keywords, and tighten up the spacing before them (" {keyword}"):
    	"COUNT SUM MIN MAX AVG LTRIM RTRIM DISTINCT CONCAT IFNULL ISNULL".split(' ').each(function(theKeyword) {
    		result = result.replace(new RegExp("\\s*\\b" + theKeyword + "\\b", 'gi'), ' ' + theKeyword);
    	});
    	
    	// Give these keywords new lines and indentation for right-aligned 6-wide formating:
    	"SELECT FROM INNER OUTER LEFT RIGHT ON SET WHERE AND OR IF THEN GROUP ORDER HAVING LIMIT".split(' ').each(function(theKeyword) {
    		var leftPadding = (theKeyword.length < 6 ? Array(7-theKeyword.length).join(" ") : '');
    		result = result.replace(new RegExp("\\b" + theKeyword + "\\s+", 'gi'), "\n" + leftPadding + theKeyword + " ");
    	});
		return result.trim();
	}
/*
//	End SECTION::SQL Beautification
****************************************************************************/

/****************************************************************************
//	SECTION::Utilities
*/
	function showLoad(message) {
		$('loadingMessageInner').set('text', $pick(message, 'Loading...'));
		$('loadingMessage').style.display = 'block';
	}
	function hideLoad() { setTimeout('$(\'loadingMessage\').style.display = \'none\'', 200); }
	
	function properInt(theValue, fallback)		{ return (isInteger(theValue + '') ? parseInt(theValue, 10) : fallback); }
	function properNumber(theValue, fallback)	{ return (isNumeric(theValue + '') ? parseFloat(theValue)   : fallback); }
	function isInteger(stringValue) { return stringValue.match(/^-?\d+$/); }
	function isNumeric(stringValue) { return stringValue.match(/^[-+]?[0-9]*\.?[0-9]+$/); }
	
	function setSelectOptions(theSelect, valueSet, labelSet) {
		theSelect = $(theSelect);
		var oldValue = theSelect.value; // save for resetting afterwards
		theSelect.options.length = 0;
		if(!labelSet) { labelSet = valueSet; }
		
		var targetIndex = 0;
		for(var i=0; i < valueSet.length; i++) {
			theSelect.options.add(new Option(labelSet[i], valueSet[i]));
			if(oldValue == valueSet[i]) targetIndex = i;
		}
		theSelect.selectedIndex = targetIndex;
	}
	function getSelectSelectedValueSet(theSelect) {
		var result = [];
		for (var i = 0; i < theSelect.options.length; i++)
			if(theSelect.options[i].selected) { result.push(theSelect.options[i].value); }
		return result;
	}
	
	function swapSections(oldSection, newSection, duration) {
		oldSection = $(oldSection);
		newSection = $(newSection);
		if(!duration) duration = 200;
		if(newSection.style.display == 'none') { // only swap if not already swapped!
			var oldSectionFx = oldSection.get('tween');
			var newSectionFx = newSection.get('tween');
			oldSectionFx.options.duration = newSectionFx.options.duration = duration;
			oldSectionFx.start('opacity', 1,0).chain(function() {
				newSection.setStyle('opacity', 0);
				oldSection.style.display = 'none';
				newSection.style.display = '';
				newSectionFx.start('opacity', 0, 1);
			});
		}
	}
	function swapNext(el)     { swapSections(el, $(el).getNext());     }
	function swapPrevious(el) { swapSections(el, $(el).getPrevious()); }
	
	function swapToSection(sectionHolder, toIndex) {
		var sectionSet = $(sectionHolder).getChildren();
		for(i = 0; i < sectionSet.length; i++) {
			if(sectionSet[i].getStyle('display') != 'none') { // found our old section, so swap!
				swapSections(sectionSet[i], sectionSet[toIndex]);
			}
		}
	}
	function dismissTool() {
		$('toolSelect').selectedIndex = 0;
		swapToSection('toolSections', 0);
	}
	
 	function confirmNonEmpty(theInput, emptyAlert) {
		if(theInput.value.trim() == '') { alert(emptyAlert); theInput.focus(); return false; }
		return true;
	}
	function isRadioChoiceMade(radioInput) {
		var result = false;
		if(radioInput.length) {
			for (var i=0; i < radioInput.length; i++) {
				result |= radioInput[i].checked;
			}
		} else
			result = radioInput.checked;
		return result;
	}
	function captureEnter(event, theFunction, arguments) {
		event = new Event(event);
		if(event.code == 13) {
			theFunction.apply(null, arguments);
			event.stopPropagation();
			return false;
		}
		return true;
	}
/*
//	End SECTION::Utilities
****************************************************************************/


/****************************************************************************
//	SECTION::Security failure handling
*/
Request = Class.refactor(Request, { 
    options: { 
        onFailure: function() {
        	if(this.status == 400) {
        		hideLoad();
        		if($('loginMessage'))
					$('loginMessage').set('text', 'Please login again to continue.');
				App.reloggingIn = true;
				swapSections('mainPage', 'loginPage');
        	}
		}
    },
    
    send: function(options) {
    	// Add stateless CSRF protection (http://appsandsecurity.blogspot.de/2012/01/stateless-csrf-protection.html)
    	var CSRFToken = Math.random() * 100000;
    	Cookie.write('CSRFToken', CSRFToken);
    	
    	// To append our CSRFToken to the data, we must serialize it out first just like the original send() does:
		var data = (options  &&  options.data) ? options.data : this.options.data;
		
    	switch(typeOf(data)) {
			case 'element': data = document.id(data).toQueryString(); break;
			case 'object': case 'hash': data = Object.toQueryString(data);
		}
    	data = (data ? data + '&' : '') + 'CSRFToken=' + CSRFToken;
		options = Object.append({ data: data }, options); // tuck our augmented POST data back in as an override option
    	this.previous(options); // with our CSRFToken inserted into the request AND set as a Cookie, we are set
	}
});

function prepFormForCSRFLegitimacy(theForm) {
	theForm = $(theForm);
	if(!theForm.CSRFToken)
		theForm.adopt(new Element('input', { 'type': 'hidden', name: 'CSRFToken' }));
    var CSRFToken = Math.random() * 100000;
    theForm.CSRFToken.value = CSRFToken;
    Cookie.write('CSRFToken', CSRFToken);
}

/*
//	End SECTION::Security failure handling
****************************************************************************/
