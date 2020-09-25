(function () {
  /*toggle menu*/

  var navToggleButton = document.querySelector('.nav__toggle-button');
  var navWrapper = document.querySelector('nav .wrapper');
  var menu = document.querySelector('.menu');
  var shedule = document.querySelector('.shedule');
  var takeClass = document.querySelector('.takeClass');
  var body = document.querySelector('body')


  navToggleButton.addEventListener('click', function () {
    navToggleButton.classList.toggle('nav__toggle-button--open');
    menu.classList.toggle('menu--open');
    if (menu.classList.contains('menu--open')) {
      navWrapper.style.overflow = 'visible';
      takeClass.style.display = 'none';
    } else {
      navWrapper.style.overflow = 'hidden';
      takeClass.style.display = 'block';
    }
    shedule.classList.toggle('visually-hidden');
    body.classList.toggle('darkBackground')
  })


})();