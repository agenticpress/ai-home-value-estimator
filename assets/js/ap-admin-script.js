document.addEventListener('DOMContentLoaded', function () {
  // --- Original "Copy to Clipboard" functionality ---
  const copyButtons = document.querySelectorAll('button[data-clipboard-target]');
  copyButtons.forEach(button => {
    button.addEventListener('click', function () {
      const targetSelector = this.getAttribute('data-clipboard-target');
      const targetElement = document.querySelector(targetSelector);
      if (targetElement) {
        navigator.clipboard.writeText(targetElement.innerText).then(() => {
          const originalText = this.innerText;
          this.innerText = 'Copied!';
          setTimeout(() => {
            this.innerText = originalText;
          }, 2000);
        }).catch(err => {
          console.error('Failed to copy text: ', err);
        });
      }
    });
  });

  // --- New API Test Area functionality ---
  const apiTestForm = document.getElementById('api-test-form');
  if (apiTestForm) {
    apiTestForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const addressInput = document.getElementById('api-test-address');
      const endpointSelect = document.getElementById('api-test-endpoint');
      const resultBlock = document.querySelector('#api-test-result code');
      const submitButton = document.getElementById('api-test-submit');
      const spinner = document.querySelector('#api-test-form .spinner');

      // Show loading state
      resultBlock.textContent = 'Fetching data...';
      submitButton.disabled = true;
      spinner.style.visibility = 'visible';

      const formData = new FormData();
      formData.append('action', 'agenticpress_api_test');
      formData.append('api_test_nonce', document.getElementById('api_test_nonce').value);
      formData.append('address', addressInput.value);
      formData.append('endpoint', endpointSelect.value);

      fetch(agenticpress_hv_admin_ajax.ajax_url, {
        method: 'POST',
        body: formData,
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          resultBlock.textContent = 'Status Code: ' + data.data.status_code + '\n\n' + data.data.raw_json;
        } else {
          resultBlock.textContent = 'Error: ' + data.data.message;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        resultBlock.textContent = 'An unknown error occurred. Check the browser console.';
      })
      .finally(() => {
        // Hide loading state
        submitButton.disabled = false;
        spinner.style.visibility = 'hidden';
      });
    });
  }
});