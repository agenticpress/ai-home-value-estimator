jQuery(document).ready(function ($) {
  let selectedAddress1 = '';
  let selectedAddress2 = '';

  const autocompleteElement = document.querySelector('gmp-place-autocomplete');

  if (autocompleteElement) {
    autocompleteElement.addEventListener('gmp-select', async (event) => {
      const placePrediction = event.placePrediction;
      if (!placePrediction) return;

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

    const form = $(this);
    const resultDiv = $('#agenticpress-hv-result');
    const submitButton = form.find('button[type="submit"]');
    const originalButtonText = submitButton.text();

    resultDiv.html('Loading...').removeClass('success error');
    submitButton.prop('disabled', true).text('Checking...');

    if (!selectedAddress1 || !selectedAddress2) {
        resultDiv.html('<strong>Error:</strong> Please select a valid address from the dropdown suggestions.').addClass('error');
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
        let html = '<h3>Property Details</h3>';
        html += '<div class="agenticpress-hv-details-grid">';
        html += '<div><strong>Estimated Value (AVM):</strong> ' + details.estimated_value + '</div>';
        html += '<div><strong>Value Range (High-Low):</strong> ' + details.avm_range + '</div>';
        html += '<div><strong>AVM Confidence Score:</strong> ' + details.confidence_score + '</div>';
        html += '<div><strong>Bedrooms:</strong> ' + details.bedrooms + '</div>';
        html += '<div><strong>Bathrooms:</strong> ' + details.bathrooms + '</div>';
        html += '<div><strong>Year Built:</strong> ' + details.year_built + '</div>';
        html += '<div><strong>Lot Size (Acres):</strong> ' + details.lot_size_acres + '</div>';
        html += '<div><strong>Property Type:</strong> ' + details.property_type + '</div>';
        html += '</div>';

        resultDiv.html(html).addClass('success');
      } else {
        resultDiv.html('<strong>Error:</strong> ' + response.data.message).addClass('error');
      }
    }).fail(function () {
      resultDiv.html('<strong>Error:</strong> An unknown server error occurred.').addClass('error');
    }).always(function () {
      submitButton.prop('disabled', false).text(originalButtonText);
    });
  });
});