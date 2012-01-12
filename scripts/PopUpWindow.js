/*
---
description: UI widget for pop-up windows: draggable, closable, dynamic content and title.

license: MIT-style

authors:
- John Larson

requires:
- core:1.4.2/Events
- core:1.4.2/Options
- core:1.4.2/Element.Event
- core:1.4.2/Element.Style
- core:1.4.2/Element.Dimensions
- more:1.4.0.1/Drag.Move
- more:1.4.0.1/Element.Position

provides: [PopUpWindow]

...
*/

var PopUpWindow = new Class({
	
	Implements: [Options, Events],
	
	options: {/*
		onClose:			function() {},
		onOpen:				function() {},
		onResize:			function(event), */
		isDraggable:		true,
		isClosable:			true,
		isResizable:		false,
		resizeLimits:		false,
		className:			'popUpWindow',
		contentDiv:			false,
		injectLocation:		null,
		width:				400,
		zIndex:				4000,
		top:				0,
		left:				0,
		URL:				null
	},
	
	initialize: function(title, options) {
		this.setOptions(options);
		this.title = title;
		
		PopUpWindow.topZIndex = Math.max(this.options.zIndex, PopUpWindow.topZIndex);
		
		var windowDiv = new Element('div', { 'styles': { 'visibility': 'hidden', 'position': 'absolute' } });
		
		this.isOpen = false;
		windowDiv.setStyle('left', this.options.left);
		windowDiv.setStyle('top',  this.options.top );
		
		var closeIconHTML  = this.options.isClosable  ? '<span class="closeIcon"></span>'  : '';
		var resizeIconHTML = this.options.isResizable ? '<span class="resizeIcon"></span>' : '';
		
		windowDiv.set('html',
		'<div class="' + this.options.className + '">' +
		' <div class="titleBar"><span>' + title + '</span>' + closeIconHTML + '</div>' +
		' <div class="content"><div class="contentHolder"></div>' + resizeIconHTML + '</div>' +
		'</div>');
		
		windowDiv.inject(this.options.injectLocation || document.body, 'bottom');
		windowDiv.titleBar  = windowDiv.getElement('.titleBar');
		windowDiv.titleSpan = windowDiv.getElement('span');
		windowDiv.closeIcon = windowDiv.getElement('.closeIcon');
		windowDiv.contentDivHolder = windowDiv.getElement('.contentHolder');
		
		windowDiv.contentDiv = (document.id(this.options.contentDiv) || new Element('div'));
		if(this.options.width) windowDiv.contentDiv.setStyle('width', this.options.width);
		windowDiv.contentDivHolder.adopt(windowDiv.contentDiv);
		if(windowDiv.contentDiv.style.display == 'none')
			windowDiv.contentDiv.setStyle('display', 'block');
		
		this.windowDiv = windowDiv;
		var self = this;
		this.windowDiv.addEvent('mousedown', function() { self.windowDiv.setStyle('z-index', PopUpWindow.topZIndex++); });
		
		if(window.IframeShim  &&  Browser.Engine.trident4)
			this.windowShim = new IframeShim(windowDiv, { display : false });
		
		if(this.options.isDraggable) {
			this.drag = new Drag.Move(windowDiv, {
				handle: windowDiv.titleBar,
				onDrag: function() {
					if(this.windowShim)
						this.windowShim.position();
				}.bind(this)
			});
			windowDiv.titleBar.setStyle('cursor', 'move');
		}
		
		if (this.options.isClosable)
			this.windowDiv.closeIcon.addEvent('click', this.close.bind(this));
		
		if(this.options.isResizable) {
			windowDiv.contentDiv.makeResizable({
				handle: windowDiv.getElement('.resizeIcon'),
				limit: this.options.resizeLimits,
				onComplete: function(theDiv, event) { self.fireEvent('resize', [event]) } // a more elegant way to do this?
			});
		}
		
		if(this.options.URL)
			this.openURL(this.options.URL);
	},
	
	
	
	getWindowDiv:	function() { return this.windowDiv; },
	getContentDiv:	function() { return this.windowDiv.contentDivHolder; },
	
	setTitle:		function(newTitle) { this.windowDiv.titleSpan.set('html', newTitle); },
	setContent:		function(contentDiv) { this.windowDiv.contentDivHolder.empty().adopt(contentDiv); },
	setContentHTML:	function(contentHTML) { this.windowDiv.contentDivHolder.set('html', contentHTML); },
	setWidth:		function(newWidth) { this.windowDiv.setStyle('width', newWidth); },
	
	close: function(event) {
		if(!this.isOpen)
			return;
		this.isOpen = false;
		
		this.fireEvent('close');
		this.windowDiv.fade('out');
		if(this.windowShim) this.windowShim.hide();
	},
	
	open: function() {
		this.windowDiv.setStyle('z-index', PopUpWindow.topZIndex++); // make this PopUpWindow the top one
		if(this.isOpen)
			return;
		this.windowDiv.fade('in');
		if(this.windowShim) this.windowShim.show();
		this.fireEvent('open');
		this.isOpen = true;
	},
	
	toggle: function() { this.isOpen ? this.close() : this.open(); },
		
	setPosition: function(options) {
		this.windowDiv.position(options);
		/* Example options = {
				relativeTo: document.body,
				position: 'center',
				edge: false,
				offset: {x: 0, y: 0}
			} */
	},
	positionTo: function(relativeTo, xOffset, yOffset) { this.setPosition(
		{ relativeTo: relativeTo, offset: { x: xOffset, y: yOffset}, position: 'top left', edge: 'top left' });
	},
	
	openURL: function(URL, newTitle, onComplete, method) {
		var self = this;
		new Request.HTML({ url: URL,
			method: method || 'post',
			update: this.windowDiv.contentDivHolder,
			evalScripts: true,
			onComplete: function() {
				self.open();
				if(newTitle)
					self.setTitle(newTitle);
				if(onComplete)
					onComplete(this.response.text);
			}
		}).send();
	}
});
PopUpWindow.topZIndex = 1;