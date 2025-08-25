jQuery(document).ready(function ($) {
  let selectedAddress1 = '';
  let selectedAddress2 = '';
  let fullAddress = '';
  let currentLookupId = null;
  let currentEstimatedValue = '';

  /**
   * Populates the hidden fields in the Gravity Form.
   * This is wrapped in a function so we can call it on success and on form re-render.
   */
  function populateGravityFormFields() {
    if (currentLookupId) {
      $('.ap-lookup-id-field input').val(currentLookupId);
      $('.ap-address-field input').val(fullAddress);
      $('.ap-estimated-value-field input').val(currentEstimatedValue);
    }
  }

  /**
   * Gravity Forms Hook: gform_post_render
   * This fires every time a form is rendered on the page (including after AJAX validation).
   * This is the most robust way to ensure our fields are populated.
   */
  $(document).on('gform_post_render', function (event, form_id, current_page) {
    populateGravityFormFields();
  });

  const autocompleteElement = document.querySelector('gmp-place-autocomplete');

  if (autocompleteElement) {
    autocompleteElement.addEventListener('gmp-select', async (event) => {
      const placePrediction = event.placePrediction;
      if (!placePrediction) return;

      fullAddress = placePrediction.text; // Store the full address string

      const place = placePrediction.toPlace();
      await place.fetchFields({ fields: ['addressComponents'] });
      if (!place.addressComponents) return;

      const getComponent = (type) => {
        const component = place.addressComponents.find(c => c.types.includes(type));
        return component ? component.longText : '';
      };

      const streetNumber = getComponent('street_number');
      const route = getComponent('route');
      const city = getComponent('locality');
      const state = getComponent('administrative_area_level_1');
      const zip = getComponent('postal_code');

      selectedAddress1 = `${streetNumber} ${route}`.trim();
      selectedAddress2 = `${city}, ${state} ${zip}`.trim();
    });
  }

  $('#agenticpress-hv-form').on('submit', function (e) {
    e.preventDefault();

    const formWrapper = $('#agenticpress-hv-form-wrapper');
    const resultContainer = $('#agenticpress-combined-result-container');
    const errorContainer = $('#agenticpress-hv-error-container');
    const submitButton = $(this).find('button[type="submit"]');
    const originalButtonText = submitButton.text();

    // Reset state
    errorContainer.hide().html('');
    resultContainer.hide();
    $('#agenticpress-ai-summary-wrapper').hide(); // Hide AI summary on new request
    submitButton.prop('disabled', true).text('Checking...');

    if (!selectedAddress1 || !selectedAddress2) {
      errorContainer.html('<strong>Error:</strong> Please select a valid address from the dropdown suggestions.').show();
      submitButton.prop('disabled', false).text(originalButtonText);
      return;
    }

    const formData = {
      action: 'agenticpress_get_home_value',
      nonce: agenticpress_hv_ajax.nonce,
      address1: selectedAddress1,
      address2: selectedAddress2,
    };

    $.post(agenticpress_hv_ajax.ajax_url, formData, function (response) {
      if (response.success) {
        const details = response.data.details;
        currentLookupId = response.data.lookup_id;
        currentEstimatedValue = details.estimated_value; // Store for use in the populate function

        // Populate the new combined results container
        $('#agenticpress-result-address').text(fullAddress);
        $('#ap-result-value').text(details.estimated_value);
        $('#ap-result-range').text(details.avm_range);
        $('#ap-result-confidence').text(details.confidence_score);

        // Check for and display the AI summary
        if (details.ai_summary) {
            $('#ap-result-ai-summary').html(details.ai_summary.replace(/\n/g, '<br>'));
            $('#agenticpress-ai-summary-wrapper').show();
        }

        // Hide the initial form and show the results
        formWrapper.hide();
        resultContainer.show();

        // Run the population function for the first time. The gform_post_render hook will
        // handle subsequent re-renders (e.g., validation errors).
        populateGravityFormFields();

      } else {
        errorContainer.html('<strong>Error:</strong> ' + response.data.message).show();
        submitButton.prop('disabled', false).text(originalButtonText);
      }
    }).fail(function () {
      errorContainer.html('<strong>Error:</strong> An unknown server error occurred.').show();
      submitButton.prop('disabled', false).text(originalButtonText);
    });
  });
});