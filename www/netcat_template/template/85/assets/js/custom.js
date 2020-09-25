// -----------------------------------------------------------------------------
// ---   РАБОТА С КОРЗИНОЙ
// -----------------------------------------------------------------------------
$(function() {

    // Селектор кнопок «Положить в корзину»
    var addToCartButtons = '.tpl-link-cart-add';


    // В шаблоне используется скрипт magnificParams. Это параметры по умолчанию для него для всплывающих сообщений:
    var magnificParams = {
        items: {},
        mainClass: 'tpl-block-mfp-animating',
        removalDelay: 200,
        type: 'inline',
        closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'
    };

    /**
     * Склонение русских слов
     */
    function pluralForm(itemQuantity, one, two, many) {
        itemQuantity = Math.abs(itemQuantity) % 100;
        var underHundred = itemQuantity % 10,
            result = many;

        if (underHundred > 1 && underHundred < 5) { result = two; }
        if (underHundred == 1) { result = one; }
        if (itemQuantity > 10 && itemQuantity < 20) { result = many; }

        return result;
    }

    /**
     * Обработчик ответа на запрос на добавление товара в корзину
     */
    function processCartResponse(response, itemIds) {
        // Обновим блок «Корзина» на странице
        var totalItems = response.TotalItemCount;
        $('.tpl-block-mobilenav-counter').html(totalItems);

        var cartHtml = totalItems
            ? 'В корзине ' +
              '<span class="tpl-property-amount">' + totalItems + ' ' + pluralForm(totalItems, 'товар', 'товара', 'товаров') + '</span>' +
              '<br>на сумму <span class="tpl-property-totals">' + response.TotalItemPriceF + '</span>'
            : 'Ваша корзина пуста';

        $('body > header .tpl-block-cart .tpl-cart-summary').html(cartHtml);

        // Сообщения о невозможности добавить выбранное количество товара в корзину
        if (response.QuantityNotifications) {
            var errorMessage = '';
            $.each(itemIds, function(id) {
                if (response.QuantityNotifications[id]) {
                    errorMessage += response.QuantityNotifications[id].Message + "<br>";
                }
            });

            if (errorMessage.length) {
                var popup = $('#cart-quantity-popup');
                popup.children('span').html(errorMessage);
                magnificParams.items = { src: popup };
                $.magnificPopup.open(magnificParams);
                return; // Выход из функции processCartResponse()
            }
        }

        // Сообщение о том, что товар успешно добавлен в корзину:
        magnificParams.items = { src: $('#cart-added-popup') };
        $.magnificPopup.open(magnificParams);
    }

    /**
     * Обработчик ошибки запроса на добавление товара в корзину
     */
    function processCartError() {
        magnificParams.items = { src: $('#cart-error-popup') };
        $.magnificPopup.open(magnificParams);
    }

    /**
     * Глобальная функция для инициализации кнопок «Положить в корзину».
     *
     * Для использования в вашем шаблоне достаточно изменить processCartResponse()
     * и processCartError(), приведённые выше
     */
    window.tpl_init_cart_buttons = function() {
        var clickEvent = 'click.cart_put';
        $(addToCartButtons)
            .off(clickEvent)
            .on(clickEvent, function(e) {
                var form = $(e.target).closest('form'),
                    itemIds = {}; // идентификаторы товаров, которые будут положены в корзину

                // Собираем все идентификторы товаров
                // 1) массив items[]
                form.find("input[name='items[]']").each(function() {
                    itemIds[this.value] = this.value;
                });
                // 2) массив cart[]
                form.find("input[name^='cart[']").each(function() {
                    var match = this.name.match(/^cart\[(\d+)]\[(.+)]$/),
                        itemId = match ? (match[1] + ":" + match[2]) : null;
                    if (itemId) { itemIds[itemId] = itemId; }
                });

                $.post(form.attr('action'), form.serialize() + "&json=1", null, 'json')
                    .success(function(response) { processCartResponse(response, itemIds); })
                    .error(processCartError);
                return false;
            }
        );
    };

    // Инициализация кнопок
    tpl_init_cart_buttons();

    if (window.tpl_on_main_content_load) {
        tpl_on_main_content_load(tpl_init_cart_buttons);
    }
});
