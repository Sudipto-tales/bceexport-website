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
            '<i class="bi bi-chevron-left"></i>',
            '<i class="bi bi-chevron-right"></i>'
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
            '<i class="bi bi-chevron-left"></i>',
            '<i class="bi bi-chevron-right"></i>'
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
            '<i class="bi bi-chevron-left"></i>',
            '<i class="bi bi-chevron-right"></i>'
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
    var name = document.getElementById('quoteName').value.trim();
    var email = document.getElementById('quoteEmail').value.trim();
    var mobile = document.getElementById('quoteMobile').value.trim();
    var subject = document.getElementById('quoteSubject').value.trim();
    var message = document.getElementById('quoteMessage').value.trim();

    if (!name || !email || !mobile || !subject || !message) {
        alert('Please fill all fields before sending.');
        return;
    }

    var text = "Hello BCE Export,\n\n" +
               "*Name:* " + name + "\n" +
               "*Email:* " + email + "\n" +
               "*Mobile:* " + mobile + "\n" +
               "*Subject:* " + subject + "\n" +
               "*Message:* " + message;

    window.open('https://wa.me/+918900379037?text=' + encodeURIComponent(text), '_blank');
}

function sendToWhatsAppContact() {
    var name = document.getElementById('name').value.trim();
    var email = document.getElementById('email').value.trim();
    var subject = document.getElementById('subject').value.trim();
    var message = document.getElementById('message').value.trim();

    if (!name || !email || !subject || !message) {
        alert('Please fill all fields before sending.');
        return;
    }

    var text = "Hello BCE Export,\n\n" +
               "*Name:* " + name + "\n" +
               "*Email:* " + email + "\n" +
               "*Subject:* " + subject + "\n" +
               "*Message:* " + message;

    window.open('https://wa.me/+918900379037?text=' + encodeURIComponent(text), '_blank');
}

