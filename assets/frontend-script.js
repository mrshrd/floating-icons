
jQuery(document).ready(function($) {
    $('.floating-icon').each(function(index) {
        $(this).css({
            'animation': 'fadeInScale 0.5s ease forwards',
            'animation-delay': (index * 0.1) + 's',
            'opacity': '0'
        });
    });
    if (!$('#floating-icons-animation').length) {
        $('head').append('<style id="floating-icons-animation">@keyframes fadeInScale { from { opacity: 0; transform: scale(0.5); } to { opacity: 1; transform: scale(1); } }</style>');
    }
});
