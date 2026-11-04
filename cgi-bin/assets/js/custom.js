/* ======================================================
   custom.js - beleivetravelconsult.com (FINAL VERSION)
   Updated: WhatsApp Float + Smooth Scroll + Mobile Fixes
   ====================================================== */

$(document).ready(function () {
	"use strict";

	/* ====================================
	   1. Vars and Inits
	   ==================================== */
	var header       = $('.header');
	var menu         = $('.menu');
	var hamburger    = $('.hamburger');
	var menuActive   = false;
	var searchActive = false;

	// Initialize everything
	setHeader();
	initHomeSlider();
	initMenu();
	initSearchTabs();
	initCtaSlider();
	initTestimonialsSlider();
	initHeaderSearch();
	initSmoothScroll();
	initFloatingWhatsApp();
	initMobileMenuAutoClose();

	// Update header on scroll & resize
	$(window).on('resize', function () {
		setHeader();
	});

	$(document).on('scroll', function () {
		setHeader();
	});

	/* ====================================
	   2. Set Header (Scrolled Effect)
	   ====================================
	function setHeader() {
		if ($(window).scrollTop() > 80) {
			header.addClass('scrolled');
		} else {
			header.removeClass('scrolled');
		}

		// Auto-close mobile menu on resize to desktop
		if (window.innerWidth > 991 && menuActive) {
			closeMenu();
		}
	}

	/* ====================================
	   3. Init Home Slider (Hero)
	   ==================================== */
	function initHomeSlider() {
		if ($('.home_slider').length) {
			var homeSlider = $('.home_slider');

			homeSlider.owlCarousel({
				items: 1,
				loop: true,
				autoplay: true,
				autoplayTimeout: 6000,
				autoplayHoverPause: true,
				smartSpeed: 1200,
				nav: false,
				dots: true,
				dotsContainer: '.home_slider_custom_dots',
				animateOut: 'fadeOut',
				animateIn: 'fadeIn'
			});

			$('.home_slider_prev').on('click', function () {
				homeSlider.trigger('prev.owl.carousel');
			});
			$('.home_slider_next').on('click', function () {
				homeSlider.trigger('next.owl.carousel');
			});

			$('.home_slider_custom_dot').on('click', function () {
				homeSlider.trigger('to.owl.carousel', [$(this).index(), 300]);
			});

			homeSlider.on('changed.owl.carousel', function (event) {
				$('.home_slider_custom_dot').removeClass('active');
				$('.home_slider_custom_dot').eq(event.item.index).addClass('active');
			});
		}
	}

	/* ====================================
	   4. Mobile Menu + Auto-Close on Link Click
	   ==================================== */
	function initMobileMenuAutoClose() {
		$('.menu_item a').on('click', function () {
			if (menuActive && window.innerWidth <= 991) {
				closeMenu();
			}
		});
	}

	/* ====================================
	   5. Init Mobile Menu
	   ==================================== */
	function initMenu() {
		if (hamburger.length && menu.length) {
			hamburger.on('click', function () {
				menuActive ? closeMenu() : openMenu();
			});

			$('.menu_close_container').on('click', function () {
				closeMenu();
			});

			menu.on('click', function (e) {
				if ($(e.target).hasClass('menu')) {
					closeMenu();
				}
			});
		}
	}

	function openMenu() {
		menu.addClass('active');
		$('body').addClass('menu-open');
		menuActive = true;
	}

	function closeMenu() {
		menu.removeClass('active');
		$('body').removeClass('menu-open');
		menuActive = false;
	}

	/* ====================================
	   6. Smooth Scrolling for Anchor Links
	   ==================================== */
	function initSmoothScroll() {
		$('a[href*="#"]').not('[href="#"]').not('[href="#0"]').click(function (event) {
			if (
				location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') 
				&& location.hostname == this.hostname
			) {
				var target = $(this.hash);
				target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
				if (target.length) {
					event.preventDefault();
					$('html, body').animate({
						scrollTop: target.offset().top - 80 // offset for fixed header
					}, 1000);
					
					// Close mobile menu if open
					if (menuActive) {
						closeMenu();
					}
				}
			}
		});
	}

	/* ====================================
	   7. Search Tabs
	   ==================================== */
	function initSearchTabs() {
		if ($('.search_tab').length) {
			$('.search_tab').on('click', function () {
				var index = $(this).index();
				$('.search_tab').removeClass('active');
				$(this).addClass('active');
				$('.search_panel').removeClass('active');
				$('.search_panel').eq(index).addClass('active');
			});
		}
	}

	/* ====================================
	   8. CTA & Testimonials Sliders
	   ==================================== */
	function initCtaSlider() {
		if ($('.cta_slider').length) {
			var ctaSlider = $('.cta_slider');
			ctaSlider.owlCarousel({
				items: 1,
				loop: true,
				autoplay: true,
				autoplayTimeout: 5000,
				smartSpeed: 1200,
				nav: false,
				dots: true
			});

				$('.cta_slider_prev').on('click', function () {
				ctaSlider.trigger('prev.owl.carousel');
			});
			$('.cta_slider_next').on('click', function () {
				ctaSlider.trigger('next.owl.carousel');
			});
		}
	}

	function initTestimonialsSlider() {
		if ($('.test_slider').length) {
			var testSlider = $('.test_slider');
			testSlider.owlCarousel({
				loop: true,
				nav: false,
				dots: true,
				smartSpeed: 1200,
				margin: 30,
				responsive: {
					0:    { items: 1 },
					768:  { items: 2 },
					992:  { items: 3 }
				}
			});

			$('.test_slider_prev').on('click', function () {
				testSlider.trigger('prev.owl.carousel');
			});
			$('.test_slider_next').on('click', function () {
				testSlider.trigger('next.owl.carousel');
			});
		}
	}

	/* ====================================
	   9. Header Search Toggle
	   ==================================== */
	function initHeaderSearch() {
		if ($('.content_search').length) {
			$('.content_search').on('click', function (e) {
				e.stopPropagation();
				$('.search_form').toggleClass('active');
				searchActive = !searchActive;

				if (searchActive) {
					$('.search_content_input').focus();
				}
			});

			$(document).on('click', function (e) {
				if (!$(e.target).closest('.search_form, .content_search').length) {
					$('.search_form').removeClass('active');
					searchActive = false;
				}
			});
		}
	}

	/* ====================================
	   10. Floating WhatsApp Button
	   ==================================== */
	function initFloatingWhatsApp() {
		// Change the phone number below to yours
		var whatsappNumber = "233554460921"; // ← PUT YOUR REAL NUMBER HERE (country code + number, no spaces)

		var whatsappHTML = `
			<a href="https://wa.me/${whatsappNumber}" target="_blank" class="whatsapp_float">
				<img src="assets/images/whatsapp.png" alt="Chat on WhatsApp">
			</a>
		`;

		$('body').append(whatsappHTML);
	}

});