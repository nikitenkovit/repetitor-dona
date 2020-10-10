function metrikaReach(goal_name, goal_params) {
  var goal_params = goal_params || {};
  for (var i in window) {
    if (/^yaCounter\d+/.test(i)) {
      window[i].reachGoal(goal_name, goal_params);
    }
  }
}

/* author Anthony Goryaynov */

/* Banners slideShow */
function rotateBanners() {
  $('.banner li').css({opacity: 0.0});
  $('.banner li:first').css({opacity: 1.0});
  setInterval('rotate()', 7000);
}

function rotate() {
  var current = ($('.banner li.show') ? $('.banner li.show') : $('.banner li:first'));
  var next = ((current.next().length) ? ((current.next().hasClass('show')) ? $('.banner li:first') : current.next()) : $('.banner li:first'));

  next.css({opacity: 0.0}).addClass('show').animate({opacity: 1.0}, 1000);
  current.animate({opacity: 0.0}, 1000).removeClass('show');
};
/* end slideShow */

$(function () {

  var modalWindow = $('.modalWindow'),
    logIn = $('a.logIn'),
    authPan = $('.auth_block'),
    callBackLink = $('.header .callBack'),
    getCallPan = $('.getCall'),
    overlay = $('#cboxOverlay');

  $('a.gallery').colorbox({rel: 'gal'});

  $('.diplomaSliderWrapper').slick({
    infinite: true,
    slidesToShow: 2,
    slidesToScroll: 1
  });

  var videoButton = $('.reviewsItemVideo').find('button');

  videoButton.on('click', function () {
    $(this).next().toggleClass('active');
  });

  function set_cookie(name, value, exp_y, exp_m, exp_d, path, domain, secure) {
    var cookie_string = name + "=" + escape(value);

    if (exp_y) {
      var expires = new Date(exp_y, exp_m, exp_d);
      cookie_string += "; expires=" + expires.toGMTString();
    }

    if (path) cookie_string += "; path=" + escape(path);
    if (domain) cookie_string += "; domain=" + escape(domain);
    if (secure) cookie_string += "; secure";
    document.cookie = cookie_string;
  }

  function get_cookie(cookie_name) {
    var results = document.cookie.match('(^|;) ?' + cookie_name + '=([^;]*)(;|$)');

    if (results)
      return (unescape(results[2]));
    else
      return null;
  }

  set_cookie ( "rightModalWindow", "0" );

  var rightModalWindow = get_cookie ( "rightModalWindow" );

  function showInterview() {
    var button = $('.showInterview, .takeInterview, .takeClass, .takeResult'),
      dialog = button.parent(),
      closeInterview = $('.closeInterview');

    button.on('click', function () {
      dialog.toggleClass('active');
      return false;
    });

    closeInterview.on('click', function () {
      dialog.removeClass('active');
      return false;
    });
  }

  var timer = setTimeout(function () {

    $('.interview').addClass('active');

  }, 0);

  function closeWindow() {
    $('#cboxOverlay, .closeModalWindow').on('click', function () {
      modalWindow.removeClass('active');
      overlay.fadeOut();
    });
  }

  $('.ajaxForm').on('submit', function () {

    var form = $(this),
      action = form.attr('action'),
      varnText = $(this).find('.warnText'),
      preloader = $(this).find('.preloader');

    preloader.css('opacity', '1');

    $.ajax({
      url: action,
      type: "POST",
      data: form.serialize(),
      success: function (data) {
        preloader.css('opacity', '0');
        varnText.fadeIn().html(data);
        setTimeout(function () {
          form.trigger('reset');
          varnText.fadeOut();
          modalWindow.removeClass('active');
          overlay.fadeOut();
        }, 3000);
        if (action.indexOf('/netcat/subscribe') !== -1) {
          metrikaReach('send_subscribe');
        } else {
          metrikaReach('send_form');
        }


      }
    });


    return false;
  });

  $('body').on('copy', function () {
    return false;
  });
  /*
      $("body").on("contextmenu", false);

      $('img').on('contextmenu', function(){
          return false;
      });
   */
  logIn.on('click', function () {
    getCallPan.removeClass('active');
    authPan.toggleClass('active');
    $('#cboxOverlay').css('display', 'block');
    return false;
  });

  callBackLink.on('click', function () {
    authPan.removeClass('active');
    getCallPan.addClass('active');
    $('#cboxOverlay').css('display', 'block');
    return false;
  });


  showInterview();
  closeWindow();
  rotateBanners();

  /*    var message="";
      function clickIE() {if (document.all) {(message);return false;}}
      function clickNS(e) {if
      (document.layers||(document.getElementById&&!document.all)) {
      if (e.which==2) {
      (message);
      return false;}}}
      if (document.layers) {
      document.captureEvents(Event.MOUSEDOWN);
      document.onmousedown=clickNS;
      }else{
      document.onmouseup=clickNS;
      document.oncontextmenu=clickIE;
      }
      document.oncontextmenu=new Function("return false")*/

  /**/


});