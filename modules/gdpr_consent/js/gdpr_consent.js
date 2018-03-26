(function ($) {
    $(function () {
        // Hide the description for any GDPR checkboxes.
        var container = $('.gdpr_consent_agreement').parent();
        var desc = container.next('.description');

        if(!desc.length) {
            container = container.parent();
            desc = container.next('.description');
        }

        desc.hide();

        $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>')
            .appendTo(container)
            .click(function () {
                var desc = $(this).next('.description');
                if(!desc.length) {
                    desc = $(this).parent().next('.description');
                }

                desc.slideToggle()
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
