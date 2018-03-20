(function ($) {
    $(function () {
        // Hide the description for any GDPR checkboxes.
        var container = $('.gdpr_consent_agreement').parent().parent();
        var desc = container.next('.description');
        desc.hide();

        $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>')
            .appendTo(container)
            .click(function () {
                $(this).parent().next('.description').slideToggle()
            });

        // Do the same for implicit
        container = $('.gdpr_consent_implicit').parent();
        desc = container.next('.description');
        desc.hide();

        $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>')
            .appendTo(container)
            .click(function () {
                $(this).next('.description').slideToggle()
            });
    });
})(jQuery);
