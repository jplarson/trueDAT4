/*
---
description: Provides inline suggestion capability for users typing in a text input.

license: MIT-style

authors:
- John Larson

requires:
- core:1.4.2/Events
- core:1.4.2/Options
- more:1.4.0.1/Element.Forms

provides: [InlineSuggest]

...
*/

var InlineSuggest = new Class({
	
	Implements: [Options, Events],
	options: {
		minLengthMatch:			3,
		exemptSet: 				[],
		suppressIfInWord:		true, 
	},
	
	initialize: function(input, suggestSet, options) {
		this.setOptions(options);
		this.input = $(input);
		this.suggestSet = suggestSet  ||  [];
		this.setExemptions(this.options.exemptSet  ||  []);
		
		this.bound = {
			acceptSuggestion: this.acceptSuggestion.bind(this),
			makeSuggestion: this.makeSuggestion.bind(this),
		};
		this.attach();
	},
	
	attach: function(attach){
		var method = (attach != false) ? 'addEvents' : 'removeEvents';
		this.input[method]({
			keypress: this.bound.acceptSuggestion,
			keyup: this.bound.makeSuggestion
		});
		this.suggestionIsPending = false;
		return this;
	},
	
	detach: function(){
		return this.attach(false);
	},
	
	acceptSuggestion: function(event) {
		if(!this.suggestionIsPending) return;
		if(this.input.getSelectedText() == '') {
			this.suggestionIsPending = false;
			return;
		}
		if([9, 13].contains(event.code)) { // tab or enter: here the user actually accepts
			var selectEnd = this.input.getSelectionEnd();
			
			// Replace the newly accepted+suggested symbol with it's exact capitalization:
			var acceptedSymbol = this.input.value.substr(0, selectEnd).match(/\b\w*$/)[0];
			for(var i=0; i < this.suggestSet.length; i++) {
				var symbol = this.suggestSet[i];
				if(symbol.toLowerCase() == acceptedSymbol.toLowerCase()) {
					this.input.value = this.input.value.substr(0, selectEnd-acceptedSymbol.length) + symbol + this.input.value.substr(selectEnd);
					break;
				}
			}
			this.input.selectRange(selectEnd, selectEnd);
			this.suggestionIsPending = false;
			event.preventDefault();
		}
		if([38, 40].contains(event.event.keyCode)) { // up or down arrow
			direction = (event.code == 38 ? -1 : 1); // up is backwards in the alphabetical suggest item list
			this.makeSuggestionFrom(this.pendingSuggestionIndex + direction, direction);
			event.preventDefault();
		}
	},
	
	makeSuggestion: function(event) {
		// Ignore cursor keys, shift, alt, etc, look only for A-Z, 0-9, etc.
		if (event.code  < 48  || (event.code > 57 && event.code < 65) ||
			(event.code > 90  && event.keyCode < 96) || event.code == 108 ||
			(event.code > 111 && event.code < 186) ||
			(event.code > 192 && event.code < 219) || event.code > 222)
			return;
		this.makeSuggestionFrom(0, 1);
	},
	makeSuggestionFrom: function(startIndex, direction) {
		var caretPosition = this.input.getCaretPosition();
		var lastSymbolMatch = this.input.value.substr(0, caretPosition).match(/\b\w*$/);
		if(!lastSymbolMatch) return;
		
		if(this.options.suppressIfInWord  &&  !this.suggestionIsPending  &&  this.input.value.substr(caretPosition, 1).match(/\w/)) return;
		
		var currentSymbol = lastSymbolMatch[0].toLowerCase();
		var symbolLength = currentSymbol.length;
		if(symbolLength < this.options.minLengthMatch  ||  this.isExempt(currentSymbol))
			return;
		
		for(var i=startIndex; i < this.suggestSet.length  &&  i >= 0; i += direction) {
			var item = this.suggestSet[i];
			if(item.substr(0, symbolLength).toLowerCase() == currentSymbol) {
				this.input.insertAtCursor(item.substr(symbolLength), true);
				this.suggestionIsPending = true;
				this.suggestionLength = item.substr(symbolLength).length;
				this.pendingSuggestionIndex = i;
				return; // suggestion is made!
			}
		}
	},
	
	isExempt: function(symbol) {
		var symbolLength = symbol.length;
		for(var i=0; i < this.exemptSet.length; i++) {
			if(this.exemptSet[i].substr(0, symbolLength).toLowerCase() == symbol) {
				return true;
			}
		}
		return false;
	},
	
	setSuggestions: function(suggestSet) {
		this.suggestSet = suggestSet.sort();
	},
	setExemptions: function(exemptSet) {
		this.exemptSet = exemptSet.sort();
	}
});