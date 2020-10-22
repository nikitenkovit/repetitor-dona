(function () {
  var navToggleButton = document.querySelector('.nav__toggle-button');
  var navWrapper = document.querySelector('nav .wrapper');
  var menu = document.querySelector('.menu');
  var shedule = document.querySelector('.shedule');
  var takeClass = document.querySelector('.takeClass');
  var body = document.querySelector('body');
  var nav = document.querySelector('nav')

  /*toggle menu*/

  var openMenu = function () {
    navWrapper.style.overflow = 'visible';
    takeClass.style.display = 'none';
    // nav.style.position = 'fixed';
  };

  var closeMenu = function () {
    navWrapper.style.overflow = 'hidden';
    takeClass.style.display = 'block';
    nav.style.position = 'relative';
  };

  var menuHandler = function () {
    navToggleButton.classList.toggle('nav__toggle-button--open');
    menu.classList.toggle('menu--open');
    if (menu.classList.contains('menu--open')) {
      openMenu();
      document.addEventListener('click', missClick);
    } else {
      closeMenu();
      document.removeEventListener('click', missClick);
    }
    shedule.classList.toggle('visually-hidden');
    body.classList.toggle('darkBackground');
  }

  navToggleButton.addEventListener('click', menuHandler)

  var missClick = function (evt) {
    var target = evt.target;
    var itsNav = target === nav || nav.contains(target);
    var itsMenu = target === menu || menu.contains(target);
    if (!itsNav && !itsMenu) {
      menuHandler();
    }
  };

  /*change justify-content value in photoSlider from mini-groups page*/

  var photoSlider = document.querySelector('.photoSlider ul');

  if (photoSlider) {
    var photoSliderAllElements = photoSlider.children;
    if (photoSliderAllElements.length > 2) {
      photoSlider.style.justifyContent = 'space-between';
    } else {
      photoSlider.style.justifyContent = 'space-evenly';
    }
  }

  /*responsive to ColorBox*/

  jQuery.colorbox.settings.maxWidth = '95%';
  jQuery.colorbox.settings.maxHeight = '95%';

  /*resize function ColorBox*/

  var resizeTimer;
  function resizeColorBox()
  {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
      if (jQuery('#cboxOverlay').is(':visible')) {
        jQuery.colorbox.load(true);
      }
    }, 300);
  }

  jQuery(window).resize(resizeColorBox);
  window.addEventListener("orientationchange", resizeColorBox, false);

  /*up-button*/

  var upBtn = document.querySelector('.up-button')

  window.onscroll = function () {
    if (window.pageYOffset > 200) {
      upBtn.classList.remove('up-button--hidden')
    } else {
      upBtn.classList.add('up-button--hidden')
    }
  }

  function backToTop() {
    if (window.pageYOffset > 0) {
      window.scrollBy(0, -80);
      setTimeout(backToTop, 10);
    }
  }

  upBtn.addEventListener('click', backToTop);

})();