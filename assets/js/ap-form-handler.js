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

  // Debounce function to prevent excessive calls
  let enforceStylingTimeout;
  function debounceEnforceStyling() {
    clearTimeout(enforceStylingTimeout);
    enforceStylingTimeout = setTimeout(enforceInputStyling, 50);
  }

  // Track if we're currently applying styles to prevent loops
  let isApplyingStyling = false;

  // Function to force styling on the Google Places input
  function enforceInputStyling() {
    if (isApplyingStyling) return; // Prevent recursive calls
    isApplyingStyling = true;

    const container = document.querySelector('#agenticpress-hv-container');
    if (!container) {
      isApplyingStyling = false;
      return;
    }

    const autocomplete = container.querySelector('gmp-place-autocomplete');
    if (!autocomplete) {
      isApplyingStyling = false;
      return;
    }

    // Get the computed background and text colors from CSS custom properties
    const computedStyle = window.getComputedStyle(autocomplete);
    const textColor = computedStyle.getPropertyValue('--gmp-mat-color-on-surface').trim();
    const backgroundColor = computedStyle.getPropertyValue('--gmp-mat-color-surface').trim();

    if (!textColor || !backgroundColor) {
      isApplyingStyling = false;
      return;
    }


    // Find all possible input elements within the autocomplete component
    const selectors = [
      'gmp-place-autocomplete input',
      'gmp-place-autocomplete [role="combobox"]',
      'gmp-place-autocomplete .mat-mdc-form-field-infix input',
      'gmp-place-autocomplete .mdc-text-field__input',
      'gmp-place-autocomplete .mat-mdc-input-element',
      'gmp-place-autocomplete .mat-mdc-form-field-input-control input',
      'gmp-place-autocomplete .mdc-filled-text-field input',
      'gmp-place-autocomplete .mat-input-element',
      'gmp-place-autocomplete .mdc-floating-label',
      'gmp-place-autocomplete .mat-mdc-floating-label'
    ];

    selectors.forEach(selector => {
      const inputs = container.querySelectorAll(selector);
      inputs.forEach(input => {
        if (input && !input.closest('[role="listbox"]') && !input.closest('[role="option"]')) {
          // Apply new styles with maximum priority (but not to dropdown elements)
          input.style.setProperty('color', textColor, 'important');
          input.style.setProperty('background-color', backgroundColor, 'important');
          input.style.setProperty('-webkit-text-fill-color', textColor, 'important');
          input.style.setProperty('caret-color', textColor, 'important');
          
        }
      });
    });

    // Try to access shadow DOM if possible
    if (autocomplete.shadowRoot) {
      const shadowSelectors = [
        'input', 
        '[role="combobox"]', 
        '.mdc-text-field__input',
        '.mat-mdc-input-element',
        '.mat-input-element',
        '.mdc-filled-text-field input',
        '.mat-mdc-form-field-input-control input'
      ];
      
      shadowSelectors.forEach(selector => {
        const shadowInputs = autocomplete.shadowRoot.querySelectorAll(selector);
        shadowInputs.forEach(input => {
          if (input && !input.closest('[role="listbox"]') && !input.closest('[role="option"]')) {
            input.style.setProperty('color', textColor, 'important');
            input.style.setProperty('background-color', backgroundColor, 'important');
            input.style.setProperty('-webkit-text-fill-color', textColor, 'important');
            input.style.setProperty('caret-color', textColor, 'important');
          }
        });
      });
    }

    // Also search for dropdown elements that might be outside the container
    const globalDropdownSelectors = [
      '[role="listbox"]',
      '[role="option"]',
      '.pac-container',
      '.pac-item',
      '[data-testid*="place"]',
      '[class*="dropdown"]'
    ];
    
    globalDropdownSelectors.forEach(selector => {
      const elements = document.querySelectorAll(selector);
      elements.forEach(element => {
        if (element) {
          element.style.setProperty('color', '#333333', 'important');
          element.style.setProperty('background-color', '#ffffff', 'important');
          
          // Also style all children
          const children = element.querySelectorAll('*');
          children.forEach(child => {
            child.style.setProperty('color', '#333333', 'important');
            child.style.setProperty('background-color', '#ffffff', 'important');
          });
        }
      });
    });

    // More aggressive Shadow DOM search
    function styleShadowDOMDropdowns() {
      // Find all elements with shadow roots
      const elementsWithShadow = document.querySelectorAll('*');
      elementsWithShadow.forEach(element => {
        if (element.shadowRoot && element.tagName !== 'GRAMMARLY-DESKTOP-INTEGRATION') {
          // Look for dropdown elements in the shadow DOM
          const shadowDropdowns = element.shadowRoot.querySelectorAll(
            '[role="listbox"], [role="option"], .pac-container, .pac-item, [data-value], [class*="dropdown"], [class*="suggestion"], div'
          );
          
          shadowDropdowns.forEach(dropdown => {
            dropdown.style.setProperty('color', '#333333', 'important');
            dropdown.style.setProperty('background-color', '#ffffff', 'important');
            
            // Style all children in the shadow dropdown
            const shadowChildren = dropdown.querySelectorAll('*');
            shadowChildren.forEach(child => {
              child.style.setProperty('color', '#333333', 'important');
              child.style.setProperty('background-color', '#ffffff', 'important');
            });
          });
        }
      });
    }
    
    // Run shadow DOM styling
    styleShadowDOMDropdowns();

    isApplyingStyling = false;
  }

  if (autocompleteElement) {
    // Apply styling when component is ready
    autocompleteElement.addEventListener('DOMContentLoaded', enforceInputStyling);
    autocompleteElement.addEventListener('gmp-load', enforceInputStyling);
    autocompleteElement.addEventListener('gmp-ready', enforceInputStyling);
    
    // Apply styling more aggressively to catch dynamic changes
    setTimeout(enforceInputStyling, 50);
    setTimeout(enforceInputStyling, 200);
    setTimeout(enforceInputStyling, 500);
    setTimeout(enforceInputStyling, 1000);
    setTimeout(enforceInputStyling, 2000);
    setTimeout(enforceInputStyling, 3000);

    // Re-apply styling on focus and blur events
    autocompleteElement.addEventListener('focusin', debounceEnforceStyling);
    autocompleteElement.addEventListener('focus', debounceEnforceStyling);

    // Add mutation observer to watch for DOM changes (but avoid infinite loops)
    const observer = new MutationObserver(function(mutations) {
      if (isApplyingStyling) return; // Don't trigger if we're already applying styles
      
      let shouldApply = false;
      mutations.forEach(function(mutation) {
        // Only trigger on childList changes (new elements added)
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          shouldApply = true;
        }
      });
      
      if (shouldApply) {
        debounceEnforceStyling();
      }
    });

    // Start observing the autocomplete element (only for new child elements)
    observer.observe(autocompleteElement, {
      childList: true,
      subtree: true
    });

    // Gentle periodic enforcement (less aggressive)
    setInterval(() => {
      if (!isApplyingStyling) {
        enforceInputStyling();
      }
    }, 5000);

    // Dynamic CSS injection approach
    let dynamicStyleSheet = null;
    
    function injectDropdownCSS() {
      if (!dynamicStyleSheet) {
        dynamicStyleSheet = document.createElement('style');
        dynamicStyleSheet.id = 'agenticpress-dynamic-dropdown-css';
        document.head.appendChild(dynamicStyleSheet);
      }
      
      dynamicStyleSheet.textContent = `
        /* Target all possible Google Places dropdown elements with maximum specificity */
        html body .dropdown[part="prediction-list"],
        html body .dropdown[part="prediction-list"] *,
        html body ul[role="listbox"],
        html body ul[role="listbox"] *,
        html body li[part="prediction-item"],
        html body li[part="prediction-item"] *,
        html body .place-autocomplete-element-row,
        html body .place-autocomplete-element-row *,
        html body .place-autocomplete-element-text-div,
        html body .place-autocomplete-element-text-div *,
        html body .place-autocomplete-element-place-name,
        html body .place-autocomplete-element-place-details,
        html body [part="prediction-item-main-text"],
        html body [part="prediction-item-match"],
        html body span.place-autocomplete-element-place-name,
        html body span.place-autocomplete-element-place-details,
        html body .place-autocomplete-element-place-result--matched,
        html body .place-autocomplete-element-place-result--matched *,
        html body .place-autocomplete-element-place-result--not-matched,
        html body .place-autocomplete-element-place-result--not-matched *,
        html body span[class*="place-autocomplete-element"],
        html body div[class*="place-autocomplete-element"],
        html body .place-result,
        html body .place-result *,
        html body [class*="prediction"],
        html body [class*="prediction"] * {
          color: #ffffff !important;
          -webkit-text-fill-color: #ffffff !important;
          border: none !important;
        }
        
        /* Hover states */
        html body li[part="prediction-item"]:hover,
        html body li[part="prediction-item"]:hover *,
        html body .place-autocomplete-element-row:hover,
        html body .place-autocomplete-element-row:hover * {
          color: #cccccc !important;
          -webkit-text-fill-color: #cccccc !important;
        }
        
        /* Force override any inline styles */
        html body ul[role="listbox"][style],
        html body li[part="prediction-item"][style],
        html body span[style*="color"],
        html body div[style*="color"] {
          color: #ffffff !important;
          -webkit-text-fill-color: #ffffff !important;
        }
      `;
      
      // Also try to directly style the elements via JavaScript - multiple attempts
      const applyDirectStyling = () => {
        const selectors = [
          '.dropdown[part="prediction-list"]',
          'ul[role="listbox"]', 
          'li[part="prediction-item"]',
          '.place-autocomplete-element-row',
          '.place-autocomplete-element-text-div',
          '.place-autocomplete-element-place-name',
          '.place-autocomplete-element-place-details',
          '.place-autocomplete-element-place-result--matched',
          '.place-autocomplete-element-place-result--not-matched',
          '[class*="place-autocomplete-element"]',
          '[class*="prediction"]',
          'span[class*="place-autocomplete"]',
          'div[class*="place-autocomplete"]'
        ];
        
        selectors.forEach(selector => {
          const elements = document.querySelectorAll(selector);
          elements.forEach(element => {
            if (element) {
              element.style.setProperty('color', '#ffffff', 'important');
              element.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
              
              // Style all children deeply
              const allChildren = element.querySelectorAll('*');
              allChildren.forEach(child => {
                child.style.setProperty('color', '#ffffff', 'important');
                child.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
              });
            }
          });
        });
      };
      
      // Apply multiple times with different delays
      setTimeout(applyDirectStyling, 50);
      setTimeout(applyDirectStyling, 100);
      setTimeout(applyDirectStyling, 200);
      setTimeout(applyDirectStyling, 500);
    }
    
    function removeDropdownCSS() {
      if (dynamicStyleSheet) {
        dynamicStyleSheet.textContent = '';
      }
    }
    
    if (autocompleteElement) {
      autocompleteElement.addEventListener('focus', () => {
        injectDropdownCSS();
      });
      
      autocompleteElement.addEventListener('blur', () => {
        setTimeout(removeDropdownCSS, 2000); // Delay to allow for selection
      });
      
      autocompleteElement.addEventListener('input', () => {
        injectDropdownCSS();
        
        // Start continuous polling to catch dropdown when it appears
        let pollCount = 0;
        const maxPolls = 50;
        const pollInterval = setInterval(() => {
          pollCount++;
          const dropdownExists = document.querySelector('ul[role="listbox"], li[part="prediction-item"], .dropdown[part="prediction-list"]');
          
          if (dropdownExists) {
            const applyAggressiveStyling = () => {
              const allPossibleElements = document.querySelectorAll(`
                ul[role="listbox"], 
                ul[role="listbox"] *, 
                li[part="prediction-item"], 
                li[part="prediction-item"] *,
                .dropdown[part="prediction-list"],
                .dropdown[part="prediction-list"] *,
                .place-autocomplete-element-row,
                .place-autocomplete-element-row *,
                span[class*="place-autocomplete"],
                div[class*="place-autocomplete"],
                [class*="prediction"],
                [class*="prediction"] *
              `);
              
              allPossibleElements.forEach(el => {
                if (el) {
                  el.style.setProperty('color', '#ffffff', 'important');
                  el.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
                }
              });
            };
            
            applyAggressiveStyling();
            setTimeout(applyAggressiveStyling, 10);
            setTimeout(applyAggressiveStyling, 50);
          }
          
          if (pollCount >= maxPolls) {
            clearInterval(pollInterval);
          }
        }, 100);
      });
    }

    // Focused mutation observer for Google dropdown detection
    const globalObserver = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) {
              // Check for any Google Places related elements
              const isGoogleElement = node.tagName === 'UL' && node.getAttribute('role') === 'listbox' ||
                                    node.tagName === 'LI' && node.getAttribute('part') === 'prediction-item' ||
                                    node.classList && (node.classList.contains('dropdown') || 
                                                     Array.from(node.classList).some(cls => cls.includes('place-autocomplete'))) ||
                                    node.tagName === 'DIV' && node.style && 
                                    (node.style.position === 'absolute' || node.style.position === 'fixed') &&
                                    node.textContent && node.textContent.includes(',');
              
              if (isGoogleElement) {
                // Apply white text styling
                const styleElement = (element) => {
                  element.style.setProperty('color', '#ffffff', 'important');
                  element.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
                  
                  // Style all children recursively
                  const allChildren = element.querySelectorAll('*');
                  allChildren.forEach(child => {
                    child.style.setProperty('color', '#ffffff', 'important');
                    child.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
                  });
                };
                
                styleElement(node);
                
                // Also apply to any newly found Google elements within this node
                const googleElements = node.querySelectorAll(`
                  ul[role="listbox"],
                  li[part="prediction-item"], 
                  .place-autocomplete-element-row,
                  [class*="place-autocomplete"],
                  [class*="prediction"]
                `);
                
                googleElements.forEach(styleElement);
              }
            }
          });
        }
      });
    });
    
    // Observe the entire document for new dropdown elements
    globalObserver.observe(document.body, {
      childList: true,
      subtree: true
    });

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

    // Prepare form data
    const baseFormData = {
      action: 'agenticpress_get_home_value',
      nonce: agenticpress_hv_ajax.nonce,
      address1: selectedAddress1,
      address2: selectedAddress2,
      website: $('#agenticpress-website').val(), // Honeypot field
      form_timestamp: $('#agenticpress-form-timestamp').val() // Timing verification
    };

    // Handle reCAPTCHA if enabled
    if (typeof agenticpress_hv_recaptcha !== 'undefined' && agenticpress_hv_recaptcha.enabled) {
      grecaptcha.ready(function() {
        grecaptcha.execute(agenticpress_hv_recaptcha.site_key, {action: 'home_value_estimate'}).then(function(token) {
          const formData = {
            ...baseFormData,
            'g-recaptcha-response': token
          };
          submitFormWithData(formData);
        });
      });
    } else {
      submitFormWithData(baseFormData);
    }
  });

  // Extract form submission logic into separate function
  function submitFormWithData(formData) {
    const formWrapper = $('#agenticpress-hv-form-wrapper');
    const resultContainer = $('#agenticpress-combined-result-container');
    const errorContainer = $('#agenticpress-hv-error-container');
    const submitButton = $('#agenticpress-hv-form').find('button[type="submit"]');
    const originalButtonText = submitButton.text();

    // Reset state
    errorContainer.hide().html('');
    resultContainer.hide();
    $('#agenticpress-ai-summary-wrapper').hide(); // Hide AI summary on new request
    submitButton.prop('disabled', true).text('Checking...');

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

        // Smooth scroll to results and prevent jumping
        setTimeout(() => {
          const resultsContainer = document.getElementById('agenticpress-combined-result-container');
          if (resultsContainer) {
            resultsContainer.scrollIntoView({ 
              behavior: 'smooth', 
              block: 'start',
              inline: 'nearest'
            });
          }
        }, 300);

      } else {
        errorContainer.html('<strong>Error:</strong> ' + response.data.message).show();
        submitButton.prop('disabled', false).text(originalButtonText);
      }
    }).fail(function () {
      errorContainer.html('<strong>Error:</strong> An unknown server error occurred.').show();
      submitButton.prop('disabled', false).text(originalButtonText);
    });
  }

  // Prevent Gravity Forms from jumping the page
  $(document).on('gform_confirmation_loaded', function(event, formId) {
    // Prevent the default scroll behavior
    event.preventDefault();
    
    // Keep the user's current scroll position
    const currentScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    // Set a small timeout to ensure the form has rendered
    setTimeout(() => {
      // Restore the scroll position to prevent jumping
      window.scrollTo(0, currentScrollPosition);
      
      // Optionally smooth scroll to the form wrapper instead of jumping
      const cmaFormWrapper = document.getElementById('agenticpress-cma-form-wrapper');
      if (cmaFormWrapper) {
        cmaFormWrapper.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center',
          inline: 'nearest'
        });
      }
    }, 100);
  });

  // Handle any other Gravity Forms events that might cause jumping
  $(document).on('gform_post_render', function(event, formId, currentPage) {
    // Prevent automatic scrolling on form re-render
    const container = document.getElementById('agenticpress-hv-container');
    if (container && container.contains(event.target)) {
      // This is our form, manage scroll behavior
      const containerRect = container.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      
      // Only scroll if the container is not visible
      if (containerRect.bottom < 0 || containerRect.top > viewportHeight) {
        container.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center',
          inline: 'nearest'
        });
      }
    }
  });
});