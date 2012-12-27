(function ($) {
  Drupal.behaviors.projectSandboxShortname = {
    attach: function (context) {
      // Only toggle the field and required label if numeric short name is turned on
      if (Drupal.settings.project.project_sandbox_numeric_shortname) {
        var $machineName = $('#edit-field-project-machine-name');

        $('#edit-field-project-type select:not(.projectSandboxShortname-processed)', context).addClass('projectSandboxShortname-processed').each(function () {
          $(this).change(function () {
            if (this.value === 'sandbox') {
              $machineName.hide();
            } else {
              // Show and add required markup to field label
              $machineName.show().trigger({
                type: 'state:required',
                trigger: true,
                value: true
              });
            }
          // Trigger to set the default value when loading the page.
          }).change();
        });
      }
    }
  };
})(jQuery);
