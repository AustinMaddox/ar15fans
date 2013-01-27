var Overall = Overall || {};

(function ($) {
	$(function(){
		Overall.init();
	});
	
	Overall.init = function(){
		$('.kill-click').click(function() {
			return false;
		});
		
		// Navigation bar clings to the top of the window 
		var $window = $(window);
		var $e = $('#nav');
		var eTop = $e.offset().top;
		$window.scroll(function() {
			$e.toggleClass('cling', $window.scrollTop() > eTop);
		});

	};
	
})(jQuery);