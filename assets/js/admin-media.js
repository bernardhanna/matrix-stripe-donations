/**
 * Matrix Donations - Admin media uploader
 */
jQuery(function($) {
	var frame;
	$('.matrix-donations-upload').on('click', function(e) {
		e.preventDefault();
		var field = $(this).data('field');
		var $wrap = $(this).closest('.matrix-donations-media-wrap');
		var $input = $wrap.find('input[type="hidden"]');
		if (frame) frame.close();
		frame = wp.media({
			title: 'Select or upload image',
			button: { text: 'Use this image' },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function() {
			var att = frame.state().get('selection').first().toJSON();
			$input.val(att.id);
			$wrap.find('.matrix-donations-media-preview').html('<img src="' + att.url + '" style="max-width:200px;height:auto;display:block;" alt="" />');
			$wrap.find('.matrix-donations-upload').text('Change image');
			if (!$wrap.find('.matrix-donations-remove').length) {
				$wrap.find('.matrix-donations-upload').after('<button type="button" class="button matrix-donations-remove" data-field="' + field + '">Remove</button>');
			}
		});
		frame.open();
	});
	$(document).on('click', '.matrix-donations-remove', function(e) {
		e.preventDefault();
		var field = $(this).data('field');
		var $wrap = $(this).closest('.matrix-donations-media-wrap');
		$wrap.find('input[type="hidden"]').val(0);
		$wrap.find('.matrix-donations-media-preview').empty();
		$wrap.find('.matrix-donations-upload').text('Select image');
		$(this).remove();
	});

	$(document).on('click', '.matrix-copy-ref', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var value = String($btn.data('value') || '');
		if (!value) return;

		var setDone = function() {
			var original = $btn.data('label') || $btn.text();
			$btn.data('label', original);
			$btn.text('Copied');
			setTimeout(function() {
				$btn.text(original);
			}, 1300);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(setDone);
			return;
		}

		var $tmp = $('<input type="text" />').val(value).css({
			position: 'absolute',
			left: '-9999px'
		});
		$('body').append($tmp);
		$tmp.trigger('focus').trigger('select');
		try {
			document.execCommand('copy');
			setDone();
		} catch (err) {}
		$tmp.remove();
	});

	var $settingsForm = $('.matrix-donations-settings-form');
	if ($settingsForm.length) {
		var sectionMap = {
			'stripe': ['Stripe & Security'],
			'pages': ['Donation Pages'],
			'content': ['Donation Content'],
			'design': ['Design & Accessibility'],
			'emails': ['Donation Emails'],
			'thankyou': ['Thank You Page']
		};
		var $sectionsRoot = $('#matrix-donations-settings-sections');
		var $headings = $sectionsRoot.children('h2');
		$headings.each(function() {
			var $h2 = $(this);
			var headingText = $.trim($h2.text());
			var tabId = 'stripe';
			$.each(sectionMap, function(key, labels) {
				if (labels.indexOf(headingText) !== -1) {
					tabId = key;
					return false;
				}
			});
			var $panel = $sectionsRoot.children('.matrix-settings-panel[data-tab="' + tabId + '"]');
			if (!$panel.length) {
				$panel = $('<div class="matrix-settings-panel" data-tab="' + tabId + '"></div>');
				$sectionsRoot.append($panel);
			}
			var $chunk = $h2.nextUntil('h2').addBack();
			$panel.append($chunk);
		});

		function activateTab(tabId) {
			$('.matrix-settings-tab').removeClass('is-active');
			$('.matrix-settings-tab[data-tab="' + tabId + '"]').addClass('is-active');
			$('.matrix-settings-panel').hide();
			$('.matrix-settings-panel[data-tab="' + tabId + '"]').show();
		}

		$('.matrix-settings-tab').on('click', function() {
			activateTab($(this).data('tab'));
		});
		activateTab('stripe');
	}
});
