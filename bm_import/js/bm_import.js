let st
(function ($, Drupal) {
  // setTimeout global

  Drupal.behaviors.customManagedFileBehavior = {
    attach: function (context, settings) {


      console.log("%cbm_import %c is running - attaching ajax-trigger-file-uploaded handler", BM.log.color1, BM.log.color2)
      // Ensure context is used correctly.
      // In vanilla JS, 'context' should be a DOM element, but we check to
      // ensure.
      const targetContext = (context instanceof Element) ? context : document;

      targetContext.querySelectorAll('[data-drupal-selector="edit-file-upload-remove-button"]:not(.processed)')
        .forEach(function (e) {
        e.classList.add('processed');
        const hiddenButton = e.closest('.panel')
          .querySelector('#edit-ajax-trigger-file-uploaded');
        if (hiddenButton) {
          // Simulate mousedown to trigger AJAX callback.
          const mouseDownEvent = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
            view: window
          });
          hiddenButton.dispatchEvent(mouseDownEvent);

          // Optionally simulate mouseup.
          const mouseUpEvent = new MouseEvent('mouseup', {
            bubbles: true,
            cancelable: true,
            view: window
          });
          hiddenButton.dispatchEvent(mouseUpEvent);
        }
      });
      const submitButton = document.querySelector('#edit-submit');
      if (submitButton) {
        submitButton.addEventListener('mousedown', function () {
          // Trigger the AJAX form submission
          $(submitButton).trigger('mousedown');
        });
      }

      // BATCH PROCESSING VOOR IMPORT


      console.log("bm_import - batch processing support script - is running")
      const message = document.getElementById('progress-message').innerText;

      if (message.indexOf('Processed') !== -1) {
        console.log('ajax vernieuwd -  click next delay started ... ')
        // Clear any existing timeout to prevent overlap.
        if (typeof st !== 'undefined') {
          console.log('timer cleared')
          clearTimeout(st);
        }
        st = setTimeout(function () {
          console.log('ajax vernieuwd -  click next ... ')
          // retest
          let message = document.getElementById('progress-message').innerText;
          if (message.indexOf('Processed') !== -1) {
            const submitButton = document.querySelector('#edit-submit');
            // Simulate mousedown to trigger AJAX callback.
            const mouseDownEvent = new MouseEvent('mousedown', {
              bubbles: true,
              cancelable: true,
              view: window
            });
            submitButton.dispatchEvent(mouseDownEvent);
          }
          // // Optionally simulate mouseup.
          // const mouseUpEvent = new MouseEvent('mouseup', {
          //   bubbles: true,
          //   cancelable: true,
          //   view: window
          // });
          // submitButton.dispatchEvent(mouseUpEvent);

          //submitButton.click()
        }, 200)
      }


    }
  }
})(jQuery, Drupal);
