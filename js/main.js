(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner();
    
    
    // Initiate the wowjs
    new WOW().init();


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.sticky-top').css('top', '0px');
        } else {
            $('.sticky-top').css('top', '-100px');
        }
    });
    
    
    // Dropdown on mouse hover
    const $dropdown = $(".dropdown");
    const $dropdownToggle = $(".dropdown-toggle");
    const $dropdownMenu = $(".dropdown-menu");
    const showClass = "show";
    
    $(window).on("load resize", function() {
        if (this.matchMedia("(min-width: 992px)").matches) {
            $dropdown.hover(
            function() {
                const $this = $(this);
                $this.addClass(showClass);
                $this.find($dropdownToggle).attr("aria-expanded", "true");
                $this.find($dropdownMenu).addClass(showClass);
            },
            function() {
                const $this = $(this);
                $this.removeClass(showClass);
                $this.find($dropdownToggle).attr("aria-expanded", "false");
                $this.find($dropdownMenu).removeClass(showClass);
            }
            );
        } else {
            $dropdown.off("mouseenter mouseleave");
        }
    });
    
    
    // Back to top button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });


    // Facts counter
    $('[data-toggle="counter-up"]').counterUp({
        delay: 10,
        time: 2000
    });


    // Header carousel - Smooth Slide Animation
    $(".header-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1800,
        autoplayTimeout: 5000,
        items: 1,
        dots: false,
        loop: true,
        nav : true,
        navText : [
            '<i class="bi bi-chevron-left" aria-label="Previous Slide"></i>',
            '<i class="bi bi-chevron-right" aria-label="Next Slide"></i>'
        ],
        animateOut: 'slideOutLeft',
        animateIn: 'slideInRight'
    });


    // Testimonials carousel - Smooth Slide Down Animation
    $(".testimonial-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1300,
        autoplayTimeout: 7000,
        center: true,
        dots: true,
        loop: true,
        nav: true,
        navText : [
            '<i class="bi bi-chevron-left" aria-label="Previous Testimonial"></i>',
            '<i class="bi bi-chevron-right" aria-label="Next Testimonial"></i>'
        ],
        animateOut: 'slideUp',
        animateIn: 'slideInDown',
        responsive: {
            0:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:3
            }
        }
    });


    // Certification carousel - Smooth Fade Animation
    $(".certification-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        autoplayTimeout: 5000,
        items: 4,
        dots: true,
        loop: true,
        nav: true,
        navText : [
            '<i class="bi bi-chevron-left" aria-label="Previous Certification"></i>',
            '<i class="bi bi-chevron-right" aria-label="Next Certification"></i>'
        ],
        animateOut: 'fadeOut',
        animateIn: 'fadeIn',
        responsive: {
            0:{
                items:1
            },
            576:{
                items:2
            },
            768:{
                items:3
            },
            992:{
                items:4
            }
        }
    });

})(jQuery);

/* WhatsApp Form Senders */
function sendToWhatsAppQuote() {
    var getVal = function(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
    var name = getVal('quoteName') || getVal('name');
    var email = getVal('quoteEmail') || getVal('email');
    var mobile = getVal('quoteMobile') || getVal('mobile');
    var subject = getVal('quoteSubject') || getVal('subject') || 'Quote Request';
    var message = getVal('quoteMessage') || getVal('message');

    if (!name || !email || !message) {
        alert('Please fill all required fields before sending.');
        return;
    }

    var text = "Hello BCE Export,\n\n" +
               "*Name:* " + name + "\n" +
               "*Email:* " + email + "\n" +
               (mobile ? "*Mobile:* " + mobile + "\n" : "") +
               "*Subject:* " + subject + "\n" +
               "*Message:* " + message;

    window.open('https://wa.me/+918900379037?text=' + encodeURIComponent(text), '_blank');
}

function sendToWhatsAppContact() {
    sendToWhatsAppQuote();
}

