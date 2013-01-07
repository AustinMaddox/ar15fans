var Overall = Overall || {};

(function ($) {
	$(function(){
		Overall.init();
	});
	
	Overall.init = function(){
		$('.kill-click').click(function() {
			return false;
		});
	};

})(jQuery);