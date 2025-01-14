define(function(require) {
	var $ = require('jquery');
	var elgg = require('elgg');

	var init = function() {
		$('.elgg-menu[data-menu-section="static"] > li.static-sortable ul').sortable({
			items: '> li',
			connectWith: '.elgg-menu[data-menu-section="static"] > li.static-sortable ul',
			forcePlaceholderSize: true,
			revert: true,
			tolerance: 'pointer',
			containment: '.elgg-menu[data-menu-section="static"]',
			start:  function(event, ui) {
				$(ui.item).find(' > a').addClass('dragged');
			},
			update: function(event, ui) {
				
				if (!$(this).is($(ui.item).parent())) {
					// only trigger update on receiving sortable
					return;
				}
				
				var $parent = $(ui.item).parent().parent();
				var parent_guid = $parent.find(' > a').attr('rel');
				var new_order = [];

				$parent.find('> ul > li > a').each(function(index, child) {
					new_order[index] = $(child).attr('rel');
				});
				
				elgg.action('static/reorder', {
					data: {
						guid: parent_guid,
						order: new_order
					}
				});
			}
		});

		$('.elgg-menu[data-menu-section="static"] li a').on('click', function(event) {
			if ($(this).hasClass('dragged')) {
				event.preventDefault();
				event.stopImmediatePropagation();
				$(this).removeClass('dragged');
			}
		});

		$('.elgg-menu[data-menu-section="static"] li a span').on('click', function(event) {

			if ($(this).closest('a').hasClass('dragged')) {
				return;
			}
			
			var href = $(this).closest('a').attr('href');
			document.location = href;

			event.preventDefault();
			event.stopImmediatePropagation();
		});
	};

	elgg.register_hook_handler('init', 'system', init);
});