/**
 * Matrix Donations - Donation form handling
 */
jQuery(document).ready(function($){

	jQuery.fn.exists = function(){
		return this.length > 0;
	};

	// Use plugin's localized data (fallback to theme's 'data' for backwards compatibility)
	var donationData = (typeof matrixDonationsData !== 'undefined') ? matrixDonationsData : (typeof data !== 'undefined' ? data : { donation_urls: {} });

	function getDateString(yearsAgo, daysAgo) {
		var d = new Date();
		d.setFullYear(d.getFullYear() - yearsAgo);
		d.setDate(d.getDate() - daysAgo);
		var day = ("0" + d.getDate()).slice(-2);
		var month = ("0" + (d.getMonth() + 1)).slice(-2);
		return d.getFullYear() + "-" + month + "-" + day;
	}

	// Date of birth on membership
	if ($('#00NRz000001cwP0').exists()) {
		$('#00NRz000001cwP0').on('change', function() {
			var inputDate = $(this).val();
			if (inputDate) {
				var dob = new Date(inputDate);
				var today = new Date();
				var age = today.getFullYear() - dob.getFullYear();
				var monthDiff = today.getMonth() - dob.getMonth();
				if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
					age--;
				}
				if (age < 18) {
					$('#minor_fields').show();
					$('#is_minor').val(1);
					$('#g_first_name').prop('required', true);
					$('#g_last_name').prop('required', true);
				} else {
					$('#minor_fields').hide();
					$('#is_minor').val(0);
					$('#g_first_name').prop('required', false);
					$('#g_last_name').prop('required', false);
				}
			}
		});
	}

	if ($('#salesforceForm').exists()) {
		var $form = $('#salesforceForm');

		// Donation type buttons (redirect to single/monthly page)
		$('.matrix-donation-type-btn').on('click', function() {
			var targetType = $(this).data('type');
			var currentType = $('#donation_type').val();
			if (targetType !== currentType && donationData.donation_urls) {
				if (targetType === 'single') {
					window.location.href = donationData.donation_urls.donation_single || '';
					return;
				}
				if (targetType === 'monthly') {
					window.location.href = donationData.donation_urls.donation_monthly || '';
					return;
				}
			}
			// Same page: toggle button states
			$('.matrix-donation-type-btn').attr('aria-pressed', 'false').removeClass('donation-btn-sr--active');
			$(this).attr('aria-pressed', 'true').addClass('donation-btn-sr--active');
			$('#donation_type').val(targetType);
		});

		// Amount buttons
		$('.matrix-amount-btn').on('click', function() {
			var amount = $(this).data('amount');
			$('.matrix-amount-btn').attr('aria-pressed', 'false').removeClass('donation-btn-sr--active');
			$(this).attr('aria-pressed', 'true').addClass('donation-btn-sr--active');
			$('#custom-amount').val('');
			$('#donation_amount').val(amount);
			updateImpactMessage(amount);
		});

		// Custom amount input
		$('#custom-amount').on('input change', function() {
			var val = $(this).val().trim();
			if (val && !isNaN(parseFloat(val)) && parseFloat(val) > 0) {
				$('.matrix-amount-btn').attr('aria-pressed', 'false').removeClass('donation-btn-sr--active');
				$('#donation_amount').val('custom');
				updateImpactMessage(parseFloat(val));
			} else {
				$('#donation_amount').val('10');
				$('.matrix-amount-btn[data-amount="10"]').click();
			}
		});

		function updateImpactMessage(amount) {
			var $msg = $('#impact-message');
			if ($msg.length && $msg.data('template')) {
				var count = Math.round(amount) || 10;
				var text = $msg.data('template').replace(/\{count\}/g, count);
				$msg.text(text);
			}
		}

		$('#salesforceForm .existing_vs_new input').change(function() {
			if (donationData.donation_urls) {
				if ($(this).val() === 'membership') {
					window.location.href = (donationData.donation_urls.donation_membership || '') + '#donation_form';
				}
				if ($(this).val() === 'renew_membership') {
					window.location.href = (donationData.donation_urls.donation_renew_membership || '') + '#donation_form';
				}
			}
		});

		$('#salesforceForm #00NQ1000005zWGD').change(function() {
			var maxDate_Yesterday = getDateString(0, 1);
			var maxDate_18Years = getDateString(18, 0);
			if ($(this).val() === '' || $(this).val() === 'Other') {
				$('#salesforceForm #show_pro').hide();
				$('#00NRz000001cwP0').attr('max', maxDate_Yesterday);
			} else {
				$('#salesforceForm #show_pro').show();
				$('#00NRz000001cwP0').attr('max', maxDate_18Years);
			}
		});

		$('#salesforceForm #donation_amount').change(function() {
			var $selectedOption = $(this).find('option:selected');
			if ($selectedOption.is('[data-pro="true"]')) {
				$('#salesforceForm #show_pro').show();
			} else {
				$('#salesforceForm #show_pro').hide();
			}
		});

		$('.salesformForm_donation').submit(function(e) {
			e.preventDefault();
			var isValid = true;
			$('.error-message').remove();
			$('#email-error, #name-error, #surname-error, #custom-amount-error, #checkout-error').addClass('hidden').text('');
			$('#email, #first_name, #last_name, #custom-amount').attr('aria-invalid', 'false');
			$('#checkout-status').text('');
			function setError(errorElId, message) {
				if (errorElId) $(errorElId).text(message).removeClass('hidden');
				if (errorElId === '#email-error') $('#email').attr('aria-invalid', 'true');
				if (errorElId === '#name-error') $('#first_name').attr('aria-invalid', 'true');
				if (errorElId === '#surname-error') $('#last_name').attr('aria-invalid', 'true');
				if (errorElId === '#custom-amount-error') $('#custom-amount').attr('aria-invalid', 'true');
			}
			var donationAmount = $('#donation_amount').val();
			if (donationAmount === 'custom') {
				var customAmount = $('#custom-amount').val().trim();
				if (customAmount === '' || isNaN(parseFloat(customAmount)) || parseFloat(customAmount) <= 0) {
					setError('#custom-amount-error', 'Please enter a valid custom donation amount.');
					$('#custom-amount').focus();
					isValid = false;
				}
			} else if (!donationAmount || donationAmount === '') {
				setError('#custom-amount-error', 'Please select a donation amount.');
				isValid = false;
			}
			var email = $('#email').val().trim();
			var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (email === '' || !emailPattern.test(email)) {
				setError('#email-error', 'Please enter a valid email address.');
				isValid = false;
			}
			var firstName = $('#first_name').val().trim();
			var lastName = $('#last_name').val().trim();
			if (firstName === '') {
				setError('#name-error', 'Name is required.');
				isValid = false;
			}
			if (lastName === '') {
				setError('#surname-error', 'Surname is required.');
				isValid = false;
			}
			if (!isValid) {
				return;
			}

			var draft = {
				donation_type: $('#donation_type').val(),
				donation_amount: $('#donation_amount').val(),
				custom_amount: $('#custom-amount').val(),
				email: email,
				first_name: firstName,
				last_name: lastName,
				checkout_nonce: donationData.checkout_intent_nonce || '',
				checkout_token: donationData.checkout_intent_token || '',
				created_at: Date.now()
			};

			var submitBtn = $(this).find('button[type="submit"]');
			submitBtn.prop('disabled', true);
			submitBtn.attr('aria-busy', 'true');
			$('#checkout-status').text('Starting secure checkout, please wait.');

			fetch(donationData.checkout_intent_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-Matrix-Donations-Nonce': draft.checkout_nonce
				},
				body: JSON.stringify(draft)
			})
			.then(function(response) {
				return response.json().then(function(data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function(result) {
				if (!result.ok || !result.data || !result.data.checkoutUrl) {
					var msg = (result.data && result.data.message) ? result.data.message : 'Unable to start secure checkout. Please try again.';
					setError('#checkout-error', msg);
					submitBtn.prop('disabled', false);
					submitBtn.attr('aria-busy', 'false');
					$('#checkout-status').text('Checkout failed to start.');
					return;
				}
				$('#checkout-status').text('Redirecting to secure payment page.');
				window.location.href = result.data.checkoutUrl;
			})
			.catch(function() {
				setError('#checkout-error', 'Network error while connecting to checkout. Please try again.');
				submitBtn.prop('disabled', false);
				submitBtn.attr('aria-busy', 'false');
				$('#checkout-status').text('Network error while connecting to checkout.');
			});
		});
	}

});
