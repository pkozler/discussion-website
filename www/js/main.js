/*
 * Navěsí funkce na elementy po načtení stránky
 */
$(document).ready(function() {

    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })

    /*
     * Zobrazí navbar při posunu myši do horní části stránky
     */
    $(window).mousemove(function(e){
        scrollTop = $(window).scrollTop();
        navbarHeight = $('#slide-navbar').height();
        
        if (scrollTop > navbarHeight) {
            
            vertical = e.pageY - scrollTop;
            $navbar = $('#slide-navbar');

            if(vertical <= $navbar.height()) {  
                $navbar.show("slide", { direction: "up" }, 100);
            }
            else {
                $navbar.hide("slide", { direction: "up" }, 100);
            }
        }
    }); 
    
    /*
     * Skryje navbar při scrollování na stránce níže
     */
    $(window).scroll(function(e){
        $navbar = $('#slide-navbar');
        
        if (!$navbar.is(':visible')) {
            if ($(this).scrollTop() > $navbar.height()) {
                $('#slide-navbar').hide("slide", { direction: "up" }, 100);
            } 
            else {
                $('#slide-navbar').show("slide", { direction: "up" }, 100);
            }
        }
        
    });

    /*
     * Přepne v dialogu na formulář zapomenutého hesla
     */
    /*$('#forgotPasswordFormButton').click(function () {
        $("#signInForm").hide("slide", { direction: "left" }, 100, function() {
            $("#forgotPasswordForm").show("slide", { direction: "right" }, 100);
            $('#frm-forgotPasswordForm-email').focus();
        });
    });*/
    
    /*
     * Přepne v dialogu na formulář přihlášení
     */
    /*$('#signInFormButton').click(function () {
        $("#forgotPasswordForm").hide("slide", { direction: "right" }, 100, function() {
            $("#signInForm").show("slide", { direction: "left" }, 100);
            $('#frm-signInForm-email').focus();
        });
    });*/
    
    /*
     * Zruší označení u předvybrané položky radio listu
     */
    //$('.unchecked-radio').removeProp('checked');
    
    /*
     * Nastaví kurzor do inputu emailu u dialogu přihlášení
     */
    /*$('#signInDialog').on('shown.bs.modal', function () {
      $('#frm-signInForm-email').focus();
    });*/
        
    /*
     * Nastaví kurzor do inputu přezdívky u dialogu registrace
     */
    /*$('#signUpDialog').on('shown.bs.modal', function () {
      $('#frm-signUpForm-nickname').focus();
    });*/
    
    /*
     * Nastaví formulář přihlášení po zavření dialogu přihlášení/zapomenutého hesla
     */
    /*$('#signInDialog').on('hidden.bs.modal', function (e) {
        $("#forgotPasswordForm").hide();
        $("#signInForm").show();
    })*/

    /*
     * Označí aktuální zvolenou položku menu
     */
    $('.main-menu a').each(function(index) {
        href = window.location.href;
        index = href.indexOf("?");
        hrefStart = index > 0 ? href.substr(0, index) : href;

        if(this.href.trim() == hrefStart) {
            $(this).parent().addClass("active");
            return false;
        };
    });
    
    /*
     * Označí aktuální zvolenou kategorii
     */
    $('.category-menu a').each(function(index) {
        href = this.href.trim();

        if(href.indexOf("category") < 0) {
            $(this).parent().addClass("active");
            return false;
        };
    });
    
	/**
	 * Výběr příspěvku/komentáře podle ID stisknutého tlačítka "Odpovědět"
	 */
	/*$('.reply-button').click(function() {
		buttonId = $(this).attr('id');
		index = buttonId.lastIndexOf('-') + 1;
		id = index > 0 ? buttonId.substr(index) : null;
		$('#reply-input').val(id);
	})*/
});

