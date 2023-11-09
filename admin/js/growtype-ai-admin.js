(function ($) {
    $(document).ready(function () {
        $('.wrap .tab .tab-header').click(function () {
            $(this).closest('.tab').toggleClass('is-active')
        })
    });
})(jQuery);
