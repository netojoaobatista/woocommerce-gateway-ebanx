jQuery(document).ready(function ($) {
    var clipboard = new Clipboard('.ebanx-button--copy');

    clipboard.on('success', function(e) {
        var $target = $(e.trigger);
        $target.addClass('ebanx-button--copy-success');

        setTimeout(function() {
            $target.removeClass('ebanx-button--copy-success');
        }, 2000);
    });

    clipboard.on('error', function(e) {
        var $target = $(e.trigger);

        $target.addClass('ebanx-button--copy-error');

        setTimeout(function() {
            $target.addClass('ebanx-button--copy-error');
        }, 2000);
    });
});