document.addEventListener('DOMContentLoaded', function () {
  // Find all buttons with the 'data-clipboard-target' attribute
  const copyButtons = document.querySelectorAll('button[data-clipboard-target]');

  copyButtons.forEach(button => {
    button.addEventListener('click', function () {
      // Get the target element's ID from the data attribute
      const targetSelector = this.getAttribute('data-clipboard-target');
      const targetElement = document.querySelector(targetSelector);

      if (targetElement) {
        // Use the modern Clipboard API to copy text
        navigator.clipboard.writeText(targetElement.innerText).then(() => {
          // Provide user feedback on success
          const originalText = this.innerText;
          this.innerText = 'Copied!';
          setTimeout(() => {
            this.innerText = originalText;
          }, 2000); // Reset text after 2 seconds
        }).catch(err => {
          console.error('Failed to copy text: ', err);
          // You could provide an error message to the user here
        });
      }
    });
  });
});