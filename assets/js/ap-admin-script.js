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

});