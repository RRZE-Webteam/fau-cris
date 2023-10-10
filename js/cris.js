jQuery(document).ready(function ($) {
    $('.cris-projects .abstract-title').click(function () {
        $header = $(this);
        $content = $header.next();
        $content.slideToggle(500, function () {
            $header.toggleClass('open');
        });
    });
});