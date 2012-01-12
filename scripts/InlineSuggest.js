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
		minLengthMatch:			3
	},
	
	initialize: function(input, suggestSet, options) {
		this.setOptions(options);
		this.input = $(input);
		this.suggestSet = suggestSet  ||  [];
		
		var self = this;
		this.input.addEvents({
			keypress:	self.acceptSuggestion.bind(self),
			keyup:		self.makeSuggestion.bind(self)
		})
		
		this.suggestionIsPending = false;
	},
	
	acceptSuggestion: function(event) {
		if(!this.suggestionIsPending) return;
		if(this.input.getSelectedText() == '') {
			this.suggestionIsPending = false;
			return;
		}
		if([9, 13].contains(event.code)) { // tab or enter
			var selectEnd = this.input.getSelectionEnd();
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
		
		var lastSymbolMatch = this.input.value.substr(0, this.input.getCaretPosition()).match(/\b\w*$/);
		if(!lastSymbolMatch) return;
		
		var currentSymbol = lastSymbolMatch[0];
		var symbolLength = currentSymbol.length;
		if(symbolLength < this.options.minLengthMatch)
			return;
		
		for(var i=startIndex; i < this.suggestSet.length  &&  i >= 0; i += direction) {
			var item = this.suggestSet[i];
			if(item.substr(0, symbolLength).toLowerCase() == currentSymbol.toLowerCase()) {
				this.input.insertAtCursor(item.substr(symbolLength), true);
				this.suggestionIsPending = true;
				this.pendingSuggestionIndex = i;
				return; // suggestion is made!
			}
		}
	},
	
	setSuggestions: function(suggestSet) {
		this.suggestSet = suggestSet;
	}
});