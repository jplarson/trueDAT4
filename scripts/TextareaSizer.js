
/*===============================================================================
	TextareaSizer.js
	John Larson
	9/26/11
	
===============================================================================*/

var TextareaSizer = new Class({

	Implements: [Options, Events, Class.Occlude],
	
	Binds: 		['resize', 'getIdealHeight'],
	
	options: {
		minHeight:				20, 				
		maxHeight:				0,		//set to 0 for no maximum
		initialResize:			true,
		heightPadding:			10,
		shrinkToFit:			false 
	},
	initialize: function(textarea, options) {
		this.setOptions(options);
		this.textarea = $(textarea);
		this.textarea.setStyle('overflow', 'hidden');
		var maxHeight = this.options.maxHeight;
		var minHeight = this.options.minHeight;
		var heightPadding = this.options.heightPadding;
		
		var self = this;
		this.textarea.addEvent('keyup', self.resize);
		if(this.options.initialResize)
			this.resize();
	},
	
	getIdealHeight: function() {
		var result = Math.max(this.options.minHeight, this.textarea.getScrollSize().y);
		if(this.options.maxHeight > 0)
			result = Math.min(result, this.options.maxHeight);
		return result;
	},
	
	resize: function() {
		var height = this.textarea.getSize().y;
		var idealHeight = this.getIdealHeight();
		if(height < idealHeight  ||  this.options.shrinkToFit ) {
			this.textarea.tween('height', idealHeight);
		}
	}
});