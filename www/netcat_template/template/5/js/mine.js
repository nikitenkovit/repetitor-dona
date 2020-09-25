$(function() {
    $(".our-pride .wrapper").each(function(indx, el) {
        var li = $("li", el),
            box = $(".box", el),
            btn = $(".btn", el),
            len = li.length - 1,
            i = 0;
        $(el).on("click", "li", function() {
            box.removeClass("visible").eq(i).addClass("visible")
        });
        $(el).on("click", ".btn", function(event) {
            event.preventDefault();
            i += $(this).is(".prev") ? -1 : 1;
            i < 0 && (i = len);
            i > len && (i = 0);
            li.eq(i).click()
        })
    })
});

$(document).ready(function() {
	var modalWindow = $('.modalWindow'),
        logIn = $('a.logIn'),
        authPan = $('.auth_block'),
        callBackLink = $('.callBack'),
        getCallPan = $('.getCall'),
        overlay = $('#cboxOverlay');

	$('a.gallery').colorbox({rel:'gal'})
	callBackLink.on('click', function(){
		$('#cboxOverlay').css('display','block');
        authPan.removeClass('active');
        getCallPan.addClass('active');
        
        return false;
    });
	logIn.on('click', function() {
        getCallPan.removeClass('active');
        authPan.toggleClass('active');
        $('#cboxOverlay').css('display','block');
        return false;
    });
	$('#cboxOverlay, .closeModalWindow').on('click', function() {
        modalWindow.removeClass('active');            
        overlay.fadeOut();
    });
	$("#wrapper").mCustomScrollbar();
	$('.teachers-sl').owlCarousel({
		autoplay: false,
		loop: true,
		responsiveClass:true,
		nav:true,
		navText: ["", ""],
		items: 5
	});

	$('.bottom-teacher').click(function(e) {
	   var hrefteach = '#' + $(this).attr("date");
	   $('.block-nfo-teachers').removeClass('hidden');
	   $('.block-nfo-teachers').fadeOut();	   
	   $(hrefteach).fadeIn();
	   e.preventDefault();
   });
   $('.tab .a').click(function(e) {
	   var hrefaa = $(this).attr("href");
	   $('.reviews-content').removeClass('hidden');
	   $('.reviews-content').fadeOut();	 
	   $(".tabs-year .tab a").removeClass("active");
	   $(this).addClass("active");  
	   $(hrefaa).fadeIn();
	   e.preventDefault();
   });
   var button = $('.showInterview, .takeInterview, .takeClass, .takeResult'),
   dialog = button.parent();
   
   button.on('click', function() {
	   dialog.toggleClass('active');
	   return false;
   });
   setInterval(function() {
        $('.interview').addClass('active');
	},60000);
	
	
	$('.ajaxForm').on('submit', function() {
		
		var form = $(this),
			action = form.attr('action'),
			varnText = $(this).find('.warnText'),
			preloader = $(this).find('.preloader');

		preloader.css('opacity', '1');	

		$.ajax({
			url: action,
			type: "POST",
	        data: form.serialize(),
			success: function( data ) {
				preloader.css('opacity', '0');	
			   	varnText.fadeIn().html('Заявка успешно отправлена!');
			   	setTimeout(function() {
			   		form.trigger('reset');
			   		varnText.fadeOut();
                    modalWindow.removeClass('active');            
                    overlay.fadeOut();
			   	}, 3000);
			   	if (action.indexOf('/netcat/subscribe') !== -1) {
			   	  metrikaReach('send_subscribe');
			    }
			   	else {
			   	  metrikaReach('send_form');
			    }

			   	
			}
		});	


		return false;
	});
   
   
 /**/
 //   var message="";
 //   function clickIE() {if (document.all) {(message);return false;}}
 //   function clickNS(e) {if
 //   (document.layers||(document.getElementById&&!document.all)) {
 //   if (e.which==2) {
//    (message);
//    return false;}}}
//    if (document.layers) {
 //   document.captureEvents(Event.MOUSEDOWN);
//    document.onmousedown=clickNS;
 //   }else{
//    document.onmouseup=clickNS;
//    document.oncontextmenu=clickIE;
//    }
//    document.oncontextmenu=new Function("return false")
/**/  
   
  
});

