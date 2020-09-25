###
 * Скрипт сгенерирован из файла main.coffee, находящегося в папке source-scripts
###

# require.config
#   baseUrl: 'assets/js'

addEvent = (evnt, elem, func) ->
  if (elem.addEventListener)  # W3C DOM
    elem.addEventListener(evnt,func,false);
  else if (elem.attachEvent) # IE DOM
    elem.attachEvent("on"+evnt, func);
  else # No much to do
    elem[evnt] = func;



log = ->
  log.history = log.history || []
  log.history.push arguments
  if this.console
    console.log Array.prototype.slice.call arguments

###
 * after 1000, -> addSec()
###
after = (ms, fn) ->
  setTimeout(fn, ms)



###
 * r 'plugin', -> block.plugin()
###
r = (m, c) ->
  cb = ->
    if typeof c is "function" then c.call(this) else true
  if require.defined(m) then cb() else require([m], cb)

###
 * IOS Hover/Active
 * На случай, если в body не добавят ontouchstart=""
###
addEvent "touchmove", ((event) ->
  # event.preventDefault()
  # touch = event.touches[0]
  # console.log "Touch x:" + touch.pageX + ", y:" + touch.pageY
), false

###
 * IOS Scale Bug
###
((doc) ->
  fix = ->
    meta.content = "width=device-width, minimum-scale=" + scales[0] + ", maximum-scale=" + scales[1]
    doc.removeEventListener type, fix, true
  addEvent = "addEventListener"
  type = "gesturestart"
  qsa = "querySelectorAll"
  scales = [0, 1]
  meta = (if qsa of doc then doc[qsa]("meta[name=viewport]") else [])
  if (meta = meta[meta.length - 1]) and addEvent of doc
    fix()
    scales = [.5, 1]
    doc[addEvent] type, fix, true
) document

# ###
#  * 60fps scrolling using pointer-events: none
#  * http://www.thecssninja.com/javascript/pointer-events-60fps
# ###
# body = document.body
# timer = 0
# window.addEventListener 'scroll', (->
#   clearTimeout timer
#   unless body.classList.contains('tpl-state-disable-hover')
#     body.classList.add 'tpl-state-disable-hover'
#   timer = setTimeout(->
#     body.classList.remove 'tpl-state-disable-hover'
#   , 500)
# ), false


###
 * После jQuery
###
(($, window) ->

  # $ ->
  #   colors = $('.tpl-block-colors')
  #   if colors[0]
  #     block = '.tpl-block-colors-color.tpl-state-current'
  #     require ['background-check'], ->
  #       BackgroundCheck.init(
  #         targets: block
  #       )

  ###
   * Общие переменные
  ###
  disabledClass = 'tpl-state-disabled'
  activeClass = 'tpl-state-active'
  currentClass = 'tpl-state-current'
  openedClass = 'tpl-state-opened'
  closedClass = 'tpl-state-closed'
  hiddenClass = 'tpl-state-hidden'
  mediaMid = 1000
  mediaMax = 1840
#  loaders = $('.tpl-block-loader')

  main_content_load_handlers = []

  ###
   * Добавляем функции, которые нужно выполнить при готовности основной части страницы
  ###
  window.tpl_on_main_content_load = on_main_content_load = (fn) ->
    if typeof fn is 'function'
      main_content_load_handlers.push(fn)


  ###
   Узнаём будущую высоту элемента с height:auto
  ###
  getAutoHeight = (el) ->
    startHeight = el.css('height')
    autoHeight = el.css('height', 'auto').css('height')
    el.css('height', startHeight)
    return autoHeight


  ###
   * Триггеры на разрешения экрана
   * $(window).on 'mediaMin' → < 320px
   * $(window).on 'mediaMid' → > 320px < 1850px
   * $(window).on 'mediaMax' → > 1850px
   * https://github.com/xoxco/breakpoints
  ###
  w = $(window)
  body = $('body')


  ###
   * @param  {Number} vw [null, 40, 1200, 1300]
   * @return {String}
  ###
  getMedia = (vw) ->
    unless vw? then vw = body.width()
    if vw >= mediaMid and vw < mediaMax then 'mediaMid'
    else if vw >= mediaMax then 'mediaMax'
    else 'mediaMin'

  ###
   * @param  {String} sizes ['min', 'mid', 'max', 'mid max']
   * @return {Boolean}
  ###
  isMedia = (sizes) ->
    error = 0
    nowMedia = getMedia()
    $.each sizes.split(' '), (i, size) ->
      unless size is nowMedia then error++
    if error > 0 then return false else true


  lastMedia = getMedia()

  w.on 'resize', ->
    vw = body.width()
    nowMedia = getMedia(vw)
    unless nowMedia is lastMedia
      lastMedia = nowMedia
      $(window).trigger(nowMedia)


  ###
   * Вверх
  ###
  # $ ->
  #   r 'sticky', ->
  #     contentBlock = $('.tpl-block-content')
  #     contentLayout = contentBlock.find('.tpl-layout-page-center').first()
  #     totop = $('<div class="tpl-block-totop">Вверх</div>').prependTo(contentLayout)

  #     bottom = $('body').outerHeight() - contentBlock.outerHeight() - contentBlock.offset().top

  #     totop.sticky
  #       bottomSpacing: bottom
  #       topSpacing: $(window).height()-100


#    $('.tpl-block-rating').each () ->
#        index = $(this).index('.tpl-block-rating');
#        $(this).addClass('tpl-state-index' + index);
#        return true;

  LAST_SWIPER_ID = 0;

  # Создает <div> и добавляет указанные классы (с префиксом tpl-block)
  div = (block_class_names, prefix = 'tpl-block-') ->
    '<div class="' +
      (prefix+n for n in block_class_names.split(/\s/)).join(' ') +
      '" />'

  ###
   * Создание разметки «свайпера» (aka «слайдера») вокруг одного указанного блока
  ###
  add_swiper_markup = (items_container, class_mod = null, prevnext = false) ->
    c = $(items_container)
    return false if c.closest('.tpl-block-swiper').length

    c.children().wrap(div('swiper-item'))
    c.wrapAll(div "swiper" + (if class_mod then " swiper--#{class_mod}" else ""))
     .wrapAll(div 'swiper-container')
     .addClass('tpl-block-swiper-wrapper')

    swiper_root = c.closest('.tpl-block-swiper')

    # append header (if any) to the swiper proper
    swiper_root.prepend(swiper_root.parent().children('h2, h3'))

    if class_mod is 'with-scrollbar'
      swiper_root.append(div 'swiper-scrollbar')

    if prevnext
      $(div('prevnext'))
        .append(div('tpl-link-prev', ''))
        .append(div('tpl-link-next', ''))
        .appendTo(swiper_root)


  # возвращает селектор для списка товаров виджета интернет-магазина
  items_list = (t) -> ".tpl-widget-netshop-#{t} .tpl-block-list-objects"

  on_main_content_load ->
    add_swiper_markup items_list('accessories'), null, true
    add_swiper_markup items_list('bought-together'), null, true
    add_swiper_markup items_list('viewed-together'), null, true
    add_swiper_markup items_list('viewed-by-user '), 'with-scrollbar'
    add_swiper_markup items_list('compare'), 'with-scrollbar'
    add_swiper_markup items_list('favorite'), 'with-scrollbar'

  ### front-page ###
  $ ->
    fp = $('.tpl-block-front-page')
    if fp.length
      add_swiper_markup(fp.find(items_list 'goods-offers'), '1or2', true)

      # add_swiper_markup(fp.find('.tpl-widget-netshop-new-goods .tpl-block-list-objects'), '1or3or4', true)
      # temporary replacement for ↑:
      add_swiper_markup(fp.find(items_list 'new-goods'), '1or2', true)

      add_swiper_markup(fp.find(items_list 'viewed-now'), '1or3or4', true)
      add_swiper_markup(fp.find(items_list 'bought-recently'), '1or3or4', true)

  ###
   * Слайдеры карточек
   * http://www.idangero.us/sliders/swiper
  ###
  init_swipers = (swipers) ->
    swipers = $(swipers)
    if swipers[0]
      r 'swiper', ->
        c = 'tpl-block-swiper'
        cd = false

        # Убираем тени
        removeShadows = (e) ->
          after 300, ->
            unless cd
              $(e).removeClass(c+'-container--grabbing')
            else removeShadows(e)

        # Добавляем тени
        addShadows = (e) ->
          cd = true
          $(e).addClass(c+'-container--grabbing')
          after 500, ->
            cd = false

        # Модификация свайпера
        isMod = (node, mod) ->
          return node.hasClass(c+'--'+mod)

        # Навесить свайпер
        swipeIt = (id, defaultOptions, localOptions) ->
          options = $.extend {}, defaultOptions, localOptions
          slider = new Swiper '.'+id, options
          $('.' + id).data('slider', slider)
          return slider

        # Опции по-умолчанию
        defaultOptions =
          speed: 200
          autoplay: ''
          mode: 'horizontal'
          loop: false  # "true" - crash opera 12 and IE 11 (?10)
          slidesPerView: 4 # переопределяется
          calculateHeight: false
          updateOnImagesReady: false
          DOMAnimation: false
          grabCursor: true
          autoResize: true
          resizeReInit: false
          cssWidthAndHeight: false #!
          wrapperClass: c+'-wrapper'
          slideClass: c+'-item'
          slideActiveClass: c+'-item--active'
          slideVisibleClass: c+'-item--visible'
          slideElement: 'div'
          noSwipingClass: c+'--no-swiping'
          moveStartThreshold: 5
          onSlideChangeStart: (e, a) ->
            addShadows(e.container)
          onSlideChangeEnd: (e, a) ->
            removeShadows(e.container)
          onTouchMove: (e, a) ->
            addShadows(e.container)
          onSlideReset: (e, a) ->
            removeShadows(e.container)
          onFirstInit: ->
            $(window).trigger('swiper-first-init')



        ###
         * Проходимся по всем свайперам по очереди
        ###
        swipers.each ->

          node = $(this)
          id = c+'-container--id' + (LAST_SWIPER_ID++)   #+ swipers.index(node)
          container = node.find('.'+c+'-container').addClass(id)
          localOptions = defaultOptions


          ###
           * Заморачиваемся с высотами
          ###
          setHeight = (newHeight) ->
            unless newHeight?
              newHeight = container.find('.tpl-block-swiper-item').first().outerHeight()
#              container.animate({height: newHeight+'px'}, 200)
            container.css('height', newHeight + 'px')
            node.data('prevHeight', newHeight)
          setHeight()
          ###
           * При ресайзе, раз в 100мс проверяет, не поменялась ли высота
           * первой карточки, и, если нужно, отправляет на изменение высоты враппера
          ###
          $(window).on 'resize.swiperHeight', ->
            clearTimeout(timeoutResize)
            timeoutResize = setTimeout ->
              prevHeight = node.data('prevHeight')
              nowHeight = container.find('.tpl-block-swiper-item').first().outerHeight()
              unless node.data('prevHeight') is nowHeight
#                  log node[0].className, 'newHeight!'
                setHeight(nowHeight)
            ,100


          ###
           * Определяем, сколько карточек может показаться
           * на текущем брекпоинте
          ###
          # getAmount = ->
          #   card = container.find('.tpl-block-cardbox').first()
          #   return Math.floor(node.outerWidth()/card.outerWidth())
          # node.data('prevAmount', getAmount())
          # defaultOptions.slidesPerView = node.data('prevAmount')
          getAmount = ->
            media = getMedia()
            if isMod(node, '1or2or3')
              switch media
                when 'mediaMin' then 1
                when 'mediaMid' then 2
                when 'mediaMax' then 3
            else if isMod(node, '1or2or4')
              switch media
                when 'mediaMin' then 1
                when 'mediaMid' then 2
                when 'mediaMax' then 4
            else if isMod(node, '1or3or4')
              switch media
                when 'mediaMin' then 1
                when 'mediaMid' then 3
                when 'mediaMax' then 4
            else if isMod(node, '1or2')
              switch media
                when 'mediaMin' then 1
                when 'mediaMid' then 2
                when 'mediaMax' then 2
            else
              if media is 'min' then 1 else 4


          amount = getAmount()
          node.data('prevAmount', amount)
          localOptions.slidesPerView = amount


          ###
           * Опции для свайпера со скроллбаром
          ###
          if isMod(node, 'with-scrollbar')
            localOptions =
              scrollContainer: false
              loop: false
              onFirstInit: ->
                # Если нужно, открываем табы в подвале после инициализации
                after 500, ->
                  w.trigger 'openFooterTabsBySwiper'
              scrollbar:
                container: ".#{id} + .#{c}-scrollbar"
                hide: false
                draggable: true
                snapOnRelease: true

          ###
           * Запускаем текущий свайпер с нужными опциями
          ###
          slider = swipeIt(id, defaultOptions, localOptions)


          ###
           * Когда меняется брекпоинт, то, если нужно,
           * пересчитываем количество карточек и их высоты
          ###
          $(window).on 'mediaMin mediaMid mediaMax', ->
            nowAmount = getAmount()
            prevAmount = node.data('prevAmount')
            # log node[0].className, getMedia(), 'prev:'+prevAmount, 'now:'+nowAmount
            unless nowAmount is prevAmount

              node.data('prevAmount', nowAmount)
              slider.params.slidesPerView = nowAmount
              slider.reInit()

          ###
           * Управление кнопками
          ###
          prevClass = 'tpl-link-prev'
          nextClass = 'tpl-link-next'
          buttons = node.find('.tpl-block-prevnext').find(".#{prevClass}, .#{nextClass}")
          buttons.on 'click', ->
            button = $(this)
            if button.hasClass(prevClass)
              slider.swipePrev()
            else
              slider.swipeNext()


  on_main_content_load ->
    init_swipers '.tpl-block-swiper'

  ###
   * Табы
  ###
  create_tabs = (target, tabs) ->
    return false if !tabs.length

    tab_container = $(div 'tabs')
    tab_switchers = $(div 'tabs-switcher').appendTo(tab_container)

    tabs.each ->
      tab_contents = $(this);
      header = tab_contents.find('h3').eq(0)
      header.remove().addClass('tpl-block-tabs-tab').appendTo(tab_switchers)
      $(div 'tabs-content').append(tab_contents).appendTo(tab_container)

    tab_container.find('.tpl-block-tabs-tab').eq(0).addClass('tpl-state-current')
    tab_container.find('.tpl-block-tabs-content').eq(0).addClass('tpl-state-current')
    tab_container.appendTo(target);

  on_main_content_load ->
    container = $('.tpl-block-full-more')
    create_tabs container, container.children('div').not('.tpl-block-tabs, :hidden')


  on_main_content_load ->
    c = 'tpl-block-tabs'
    q = '.' + c
    if $(q)[0]
      $(q).each ->
        node = $(this)

        tabs    = node.find(q+'-tab')
        content = node.find(q+'-content')
        wrapper = node.find(q+'-wrapper')
        switcher = node.find(q+'-switcher')
        isFooterTabs = if node.hasClass(c+'--footer') then true else false

        if isFooterTabs
          trigger = node.find(q+'-trigger')

        # Подвальные карточки
        if isFooterTabs
          w.on 'openFooterTabs', ->
            wrapperAutoHeight = getAutoHeight(wrapper)
            wrapper.removeClass(hiddenClass)
            trigger.addClass(activeClass)
            #wrapper.stop().animate({height: wrapperAutoHeight}, 200).css()
            wrapper.css('height', 'auto')
          w.on 'closeFooterTabs', ->
            wrapper.addClass(hiddenClass)
            trigger.removeClass(activeClass)
            wrapper.stop().animate({height: 0}, 200)
          # Открываем подвальные карточки автоматически,
          # свайпер триггирует после загрузки
          w.on 'openFooterTabsBySwiper', ->
            if trigger.hasClass(activeClass)
              w.trigger 'openFooterTabs'
          # Скрывает / показывает блок с контентом по клику на стрелку
          trigger.on 'click', ->
            if trigger.hasClass(activeClass)
              w.trigger 'closeFooterTabs'

            else w.trigger 'openFooterTabs'


        # Переключалка табов
        tabs.on 'click', ->
          prevTab = tabs.filter('.'+currentClass)
          nextTab = $(this)
          prevIndex = tabs.index(prevTab)
          nextIndex = tabs.index(nextTab)
          prevContent = content.eq(prevIndex)
          nextContent = content.eq(nextIndex)

          # Если в подвале, то открываем блок при нажатии на любой таб
          # И закрываем при нажатии на активный таб
          if isFooterTabs
            unless trigger.hasClass(activeClass)
              w.trigger 'openFooterTabs'
#              else if nextIndex is prevIndex
#                w.trigger 'closeFooterTabs'

          # Нажатие на активный таб (для аккордионов)
          if nextIndex is prevIndex and not isFooterTabs
            return false
#              nextContent.stop().animate({height: 0}, 200)
#              nextContent.removeClass(currentClass)
#              nextTab.removeClass(currentClass)

          else
            oldHeight = prevContent.css('height')
            newHeight = getAutoHeight(nextContent)

            prevTab.removeClass(currentClass)
            nextTab.addClass(currentClass)

            prevContent.removeClass(currentClass).css('height', 0)
            # prevContent.stop().animate({height: 0}, 200).removeClass(currentClass)
            nextContent.stop().css('height', oldHeight).animate({height: newHeight}, 200).addClass(currentClass)

            tabs.removeClass(currentClass)
            nextTab.addClass(currentClass)

            # oldHeight = content
            # newHeight = content.eq(0).css('height', 'auto').outerHeight()
            # content.stop().animate({height: 0}, 200, ->
            #   $(this).removeClass(currentClass)
            #   content.eq(nextIndex).stop().animate({height: autoHeight}, 200, ->
            #     $(this).addClass(currentClass)
            #   )
            # )

            # content.stop().slideUp(200, ->
            #   tabs.removeClass(currentClass)
            #   $(this).removeClass(currentClass)
            # )
            # content.eq(i).stop().slideDown(200, ->
            #   tab.addClass(currentClass)
            #   $(this).addClass(currentClass)
            # )

          # unless tab.hasClass(currentClass)
          #   # i = tab.index(tabs)
          #   i = tabs.index(tab)
          #   tabs.removeClass(currentClass)
          #   tab.addClass(currentClass)
          #   content.removeClass(currentClass)
          #   content.eq(i).addClass(currentClass)
          #

          unless node.hasClass(c + '--footer')

            # Табы / аккордион
            tabsToAccordion = ->
              tabs.each ->
                nextTab = $(this)
                i = tabs.index(nextTab)
                nextTab.insertBefore(content.eq(nextIndex))

            accordionToTabs = ->
              tabs.appendTo(switcher)

            if isMedia('mediaMin')
              tabsToAccordion()
            $(window).on 'mediaMin', ->
              tabsToAccordion()
            $(window).on 'mediaMid mediaMax', ->
              accordionToTabs()

  ###
   * Фильтр каталога в телефоне
  ###
  $ ->
    q = 'aside .tpl-block-filter'
    if $(q)[0]
      drop = $(q)
      drop.find('h3').first().on 'click', ->
        cssHeight = drop.css('height')
        dropHide = ->
          drop.stop().animate({height: 150}, 200)
          drop.removeClass(activeClass)
        dropShow = ->
          autoHeight = drop.css('height', 'auto').outerHeight()
          drop.height(cssHeight).stop().animate({height: autoHeight}, 200)
          drop.addClass(activeClass)
        if drop.hasClass(activeClass)
          dropHide()
        else
          dropShow()

#    ###
#     * Выбор цвета на телефоне
#    ###
#    $ ->
#      q = '.tpl-block-colors'
#      if $(q)[0]
#        drop = $(q)
#        colors = $(q + '-item')
#        colors.on 'mousedown', ->
#          # console.log($(this).children().eq(1))
#          if isMeida('min')
#            # console.log 'click'
#            color = $(this)
#            cssHeight = drop.css('height')
#            curHeight = drop.height()
#            if drop.hasClass(activeClass)
#              drop.stop().animate({height: 85}, 200)
#              drop.removeClass(activeClass)
#            else
#              autoHeight = drop.css('height', 'auto').outerHeight()
#              drop.height(cssHeight).stop().animate({height: autoHeight}, 200)
#              drop.addClass(activeClass)


  ###
   * Мобильная корзина
  ###
  $ ->
    q = '.tpl-block-mobilecart'
    if $(q)[0]
      node = $(q)
      node.find(q + '-showimage').on 'click', ->
        showimg = $(this)
        item = showimg.parents(q + '-item')
        image = item.find('.tpl-property-image')
        image.stop().slideToggle(200)
        item.toggleClass(activeClass)
      node.find(q + '-delete').on 'click', ->
        del = $(this)
        item = del.parents(q + '-item')
        item.stop().slideUp(200)



  ###
   * Мобильный каталог
  ###
  $ ->
    q = '.tpl-block-mobilemenu'
    if $(q)[0]
      menu = $(q)
      titles = menu.find(q + '-title')
      cats = menu.find(q + '-category')
      drops = menu.find(q + '-drop')
      titles.on 'click', ->
        title = $(this)
        cat = title.parents(q + '-category')
        drop = title.siblings(q + '-drop')
        if cat.hasClass(activeClass)
          drop.stop().slideUp(200)
          cat.removeClass(activeClass)
        else
          drops.stop().slideUp(200)
          cats.removeClass(activeClass)
          cat.addClass(activeClass)
          drop.stop().slideDown(200)


  ###
   * Выпадайки в мобильной навигации
  ###
  $ ->
    menuClass = 'tpl-block-mobilenav'
    dropsSelector = '.tpl-block-headerdrop'
    menuSelector = '.' + menuClass
    itemClass = menuClass + '-item'
    itemSelector = '.' + itemClass
    if $(menuSelector)[0]
      items = $(itemSelector)
      links = $('a', itemSelector)
      drops = $(dropsSelector)
      links.on 'click', (e) ->
        item = $(this).parents(itemSelector)
        unless item.hasClass(itemClass + '--logo')
          e.preventDefault()
          closeDrops = ->
            items.removeClass(activeClass)
            drops.removeClass(activeClass)
            drops.stop().slideUp(200)
          openDrop = (id) ->
            closeDrops()
            drop = $(dropsSelector + '--' + id)
            drop.addClass(activeClass)
            drop.stop().slideDown(200)
            item.addClass(activeClass)
          if item.hasClass(activeClass)
            closeDrops()
          else
            if item.hasClass(itemClass + '--menu') then openDrop('menu')
            if item.hasClass(itemClass + '--search') then openDrop('search')
            if item.hasClass(itemClass + '--cart') then openDrop('cart')
            if item.hasClass(itemClass + '--user') then openDrop('user')



  ###
   * Счётчик с плюсом/минусом
  ###
  add_qty_buttons_markup = (input) ->
    input = $(input)
    block = $(div 'amountchoice').append(
                $(div 'amountchoice-value').append(
                  $('<input />', {type: 'hidden', value: input.val(), name: input.attr('name') }),
                  '<span>' + input.val() + '</span> ',
                  input.data('units') || ''
                ),
                $(div 'amountchoice-buttons').append(
                  $(div 'amountchoice-button amountchoice-button--more').append('<i class="icon-angle-up"></i>'),
                  $(div 'amountchoice-button amountchoice-button--less').append('<i class="icon-angle-down"></i>')
                )
            )
    input.replaceWith block
    true

  init_qty_buttons = ->
    c = 'tpl-block-amountchoice'
    q = '.' + c
    clickEvent = 'click.qty_buttons'

    if $(q)[0]
      blockSelector = q
      valueSelector = q + '-value span'
      buttonSelector = q + '-button'
      moreClass = c + '-button--more'
      lessClass = c + '-button--less'

      # Пробегаемся по всем счетчикам на наличие единицы в значении
      # Если находим, то дизейблим кнопку уменьшения
      $(valueSelector).each ->
        self = $(this)
        i = parseInt(self.text())
        if i is 1
          block = self.parents(blockSelector)
          block.find('.'+lessClass).addClass(disabledClass)

      $(buttonSelector)
        .off(clickEvent)
        .on clickEvent, ->
          button = $(this)
          unless button.hasClass(disabledClass)
            block = button.parents(blockSelector)
            value = block.find(valueSelector)
            valueInput = value.siblings('INPUT');
            buttons = block.find(buttonSelector)
            i = parseInt(value.text())
            if i > 0
              if button.hasClass(moreClass)
                value.text(i+1)
                valueInput.val(i + 1)
                buttons.removeClass(disabledClass)
              else
                if i-1 is 1
                  button.addClass(disabledClass)
                valueInput.val(i - 1)
                value.text(i-1)

  on_main_content_load ->
    inputs = $('.tpl-block-full.tpl-component-goods input[name^="cart["],
                .tpl-component-cart input[name^="cart["]').not(':hidden')
    inputs.each -> add_qty_buttons_markup(this)
    init_qty_buttons()

  ###
   * Изменённые селектбоксы
   * http://harvesthq.github.io/chosen/
  ###
  init_chosen = ->
    selects = $('select')   # .tpl-block-iselect
    if selects[0]
      r 'chosen', ->
        selects.chosen
          #disable_search_threshold: 10
          disable_search: true
          width: '100%'
        #selects.on 'change', ->
        #  $(window).trigger('s')
        selects.trigger('chosen:updated')

  on_main_content_load ->
    init_chosen() if !isMedia('mediaMin')

  $(window).on 'mediaMin', ->
    $('select').chosen('destroy')

  $(window).on 'mediaMid mediaMax', ->
    init_chosen()


  # ###
  # * Форматирование цены в инпутах
  # * https://github.com/flaviosilveira/Jquery-Price-Format
  # ###
  # $ ->
  #   priceInputs = $('.tpl-block-price')
  #   if priceInputs[0]
  #     require ['price-format'], ->
  #       priceInputs.each ->
  #         input = $(this)
  #         input.priceFormat
  #           prefix: ''
  #           suffix: 'р.'


  ###
  * Слайдеры значений
  * http://refreshless.com/nouislider/
  ###
  $ ->
    q = '.nc_netshop_filter_row_range'
    range_rows = $(q)

    return false if (!range_rows.length)

    if range_rows[0]
      r 'range-slider', ->
#          r 'price-format', ->
          range_rows.each ->
            row = $(this)

            # add markup
            slider = $(div 'range-slider').appendTo(row)

            rangeMin = row.data('min')
            rangeMax = row.data('max')

            minInput = row.find('.nc_netshop_filter_field_min input').first()
            maxInput = row.find('.nc_netshop_filter_field_max input').first()

            filterMin = minInput.val().replace(/[^0-9.]/g, '') || rangeMin
            filterMax = maxInput.val().replace(/[^0-9.]/g, '') || rangeMax

            if (filterMin.toString().match(/\./) || filterMax.toString().match(/\./))
              step = 0.01
              resolution = 0.1
            else
              step = resolution = 1

#              setFormating = ->
#                minInput.add(maxInput).priceFormat
#                  prefix: ''
#                  suffix: ' руб.'
#                  # centsSeparator: ' '
#                  # thousandsSeparator: ' '

            slider.noUiSlider
              range: [rangeMin, rangeMax]
              start: [filterMin, filterMax]
              handles: 2
              # connect: true
              step: step
              serialization:
                to: [minInput, maxInput]
                resolution: resolution
#                slide: ->
#                  setFormating()

#              setFormating()


  set_cookie = (key, value, days=3650) ->
    date = new Date
    date.setTime(date.getTime() + days*24*60*60*1000)
    document.cookie = "#{key}=#{value}; path=/; expires=" + date.toGMTString()

  layout_cookie = 'tpl_default_list_layout'

  ###
   * Каталог
  ###
  on_main_content_load ->
    list = $('.tpl-block-list.tpl-component-goods .tpl-block-list-objects')

    if list.length
      ###
       * Переключалка список / плитка
      ###
      plateClass = 'tpl-layout-tiles'
      listClass = 'tpl-layout-list'
      switchToPlate = $('.tpl-block-list-layout-tiles')
      switchToList = $('.tpl-block-list-layout-list')
      switchToPlate.on 'click', ->
        switchToList.removeClass(activeClass)
        switchToPlate.addClass(activeClass)
        list.removeClass(listClass).addClass(plateClass)
        set_cookie layout_cookie, 'tiles'
      switchToList.on 'click', ->
        switchToPlate.removeClass(activeClass)
        switchToList.addClass(activeClass)
        list.removeClass(plateClass).addClass(listClass)
        set_cookie layout_cookie, 'list'

  ###
   * Галереи
   * http://fotorama.io
  ###
  on_main_content_load ->
    fotoraming = (galleries) ->
      galleries.each ->
        $(this).fotorama
          nav: 'thumbs'
          # click: false
          arrows: false
          # nav: 'none'
          height: 440
          thumbwidth: 90
          thumbheight: 90
          thumbmargin: 20
          thumbborderwidth: 5


    galleries = $('.tpl-block-gallery')
    if galleries[0]
      r 'fotorama', ->
        fotoraming(galleries)

    $(window).on 'galleryPopup', ->
      galleries = $('.tpl-block-gallery')
      r 'fotorama', ->
        fotoraming(galleries)


  ###
   * Слайдер на главной
   * http://fotorama.io
  ###
  $ ->
    q = '.tpl-block-hero'
    if $(q)[0]
      hero = $(q)
      slider = hero.find(q + '-slider')
      prev = hero.find('.tpl-link-prev')
      next = hero.find('.tpl-link-next')

      getHeight = () ->
        if isMedia('mediaMin') then return 630 else return '100%'
      setHeight = (f, h) ->
        f.resize
          height: h

      r 'fotorama', ->

        slider.on 'fotorama:ready', (e, fotorama, extra) ->
          setHeight(fotorama, getHeight())
          $(window).on 'mediaMin mediaMid mediaMax', ->
            setHeight(slider, getHeight())

        slider.fotorama
          nav: 'none'
          arrows: false
          # click: false
          margin: 20
          height: '100%'
          width: '100%'
          loop: true
          shadows: false

        f = slider.data('fotorama')

        prev.on 'click', ->
          f.show('<')
        next.on 'click', ->
          f.show('>')

  ###
   * Сравнение
   * http://www.fixedheadertable.com
  ###
  $ ->
    c = 'tpl-block-comparision'
    q = '.' + c
    if $(q)[0]
      node = $(q)
      viewport = node.find(q + '-original')

      doubler = node.find(q + '-doubler')
      doubler = viewport.clone().removeClass(c + '-original').addClass(c + '-doubler')
      doubler.find('td').detach()
      doubler.insertAfter(viewport)

      area = viewport.find('table')

      prev = doubler.find('.tpl-block-prevnext-button--prev').first()
      next = doubler.find('.tpl-block-prevnext-button--next').first()
      pos = 0

      prev.addClass(disabledClass)
      col = area.find('td').first()

      nodeWidth = node.outerWidth()
      areaWidth = area.outerWidth()
      colw = col.outerWidth()

      calc = ->
        nodeWidth = node.outerWidth()
        areaWidth = area.outerWidth()
        colw = col.outerWidth()

      if area.find('tr:first td').length <= 3
        next.addClass(disabledClass)

      $(window).on 'mediaMid mediaMax', ->
        calc()
        viewport.scrollLeft(colw * pos)

      next.on 'click', ->
        l = viewport.scrollLeft()
        if l + colw + nodeWidth >= areaWidth
          next.addClass(disabledClass)
        if l + nodeWidth < areaWidth
          prev.removeClass(disabledClass)
          viewport.animate
            scrollLeft: '+=' + colw
          , 200, ->
            pos++

      prev.on 'click', ->
        l = viewport.scrollLeft()
        if l - colw <= 0
          prev.addClass(disabledClass)
        if l > 0
          next.removeClass(disabledClass)
          viewport.animate
            scrollLeft: '-='+colw
          , 200, ->
            pos--


  ###
   * Перемещение блока с новостями в мобильной версии вниз
  ###
  $ ->
    if $('.tpl-block-front-page')[0]
      news = $('.tpl-block-news')
      parent = news.parent()

      if isMedia('mediaMin')
        news.appendTo(parent)

      $(window).on 'mediaMin', ->
        news.appendTo(parent)

      $(window).on 'mediaMid mediaMax', ->
        news.insertAfter(parent.children('aside'))


  ###
   * Лайтбоксы
   * http://dimsemenov.com/plugins/magnific-popup/
  ###
  on_main_content_load ->
    r 'magnific-popup', ->
      $('.tpl-link-modal').magnificPopup
        midClick: true
        mainClass: 'tpl-block-mfp-animating'
        removalDelay: 200
        type: 'inline'
        closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'


  ###
   * Переход по ссылке «Подробное описание» в полном просмотре товаров
  ###
  on_main_content_load ->
    $('.tpl-block-full.tpl-component-goods .tpl-block-details-brief a.tpl-link-more').on 'click', ->
      tabSwitcher = $('.tpl-block-tabs-switcher .tpl-block-tabs-tab').eq(0).click()
      $('HTML, BODY').animate
        scrollTop: tabSwitcher.offset().top
      return false
    return

  ###
   * Добавление/удаление в сравнение и избранное
  ###
  footer_tabs = {
    recent:
      tab_index: 0
      source: '.tpl-widget-netshop-viewed-by-user'
    compare:
      tab_index: 1
      source: '.tpl-widget-netshop-compare'
      additional_content: '.tpl-block-compare-button'
      link: '.tpl-link-compare-add, .tpl-link-compare-remove'
      popups: '#goodslist-compare-add, #goodslist-compare-remove'
      actions:
        '.tpl-link-compare-add':
          links_to_show: '.tpl-link-compare-remove, .tpl-link-compare'
          popup: '#goodslist-compare-add'
        '.tpl-link-compare-remove':
          links_to_show: false #'.tpl-link-compare-add'
          popup: false #'#goodslist-compare-remove'
    favorite:
      tab_index: 2
      source: '.tpl-widget-netshop-favorite'
      link: '.tpl-link-favorite-add, .tpl-link-favorite-remove'
      popups: '#goodslist-favorite-add, #goodslist-favorite-remove'
      actions:
        '.tpl-link-favorite-add':
          links_to_show: '.tpl-link-favorite-remove, .tpl-link-favorite'
          popup: '#goodslist-favorite-add'
        '.tpl-link-favorite-remove':
          links_to_show: false #'.tpl-link-favorite-add'
          popup: false #'#goodslist-favorite-remove'
  }


  # Что происходит при обновлении страницы при изменении состава списков товаров
  on_footer_data_load = (data) ->
    scroll_top = $(window).scrollTop()
    $data = $('<div/>').html(data)
    footer = $('.tpl-block-tabs--footer .tpl-block-tabs-content')
    for tab_name, tab of footer_tabs
      new_items = $data.find(tab.source);
      current_tab = footer.eq(tab.tab_index)

      swiper = current_tab.find('.tpl-block-swiper-container').data('slider')
      swiper_x = if swiper then swiper.getWrapperTranslate('x') else null

      current_tab.empty().append(new_items)

      if tab.additional_content
          current_tab.append($data.find(tab.additional_content))

      add_swiper_markup(current_tab.find('.tpl-block-list-objects'), 'with-scrollbar')
      init_swipers(current_tab.find('.tpl-block-swiper'))

      # восстановим прежнее положение свайпера
      new_swiper = current_tab.find('.tpl-block-swiper-container').data('slider')
      if new_swiper and swiper_x
        new_swiper.setWrapperTranslate(swiper_x, 0, 0)

      if current_tab.is('.tpl-state-current')
        current_tab.css('height', 'auto')


    # восстановим старый scrollTop, чтобы прокрутка не скакнула
    $(window).scrollTop(scroll_top)
    tpl_init_cart_buttons() if window.tpl_init_cart_buttons
    return true


  on_main_content_load ->
    click = 'click.footer_lists_link'
    $(document).off click

    # (1) Нажатие на ссылку «Уже в избранном»
    $(document).on click, '.tpl-link-favorite', -> scroll_to_tab(footer_tabs.favorite.tab_index)

    # (2) Нажатие на ссылку «Добавить в список» / «Удалить из списка»
    for tab_name, tab_settings of footer_tabs
      continue unless tab_settings.link
      $(document).on click, tab_settings.link, tab_name, (event) ->
        event.preventDefault()
        tab = footer_tabs[event.data]
        $this = $(this)
        href = $this.attr('href')

        if href != '#'
          # Обновление блока — перезагрузка страницы
          $.ajax
            url: href
            dataType: 'html'
            success: on_footer_data_load

        # Переключение ссылок
        $this.hide()
        for this_class, actions of tab.actions when $this.is(this_class)
          actions = $.extend({}, actions);
          if actions.links_to_show
            $this.siblings(actions.links_to_show).show()
          else
            $this.show()

          if actions.popup
            # Показ сообщения
            do (popup = actions.popup) ->
              r 'magnific-popup', ->
                $.magnificPopup.open
                  items:
                    src: popup
                  mainClass: 'tpl-block-mfp-animating'
                  removalDelay: 200
                  type: 'inline'
                  closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'

        # Нажатие на ссылку в всплывающем окне «Добавлено в [список]»
        $(tab.popups).find('.tpl-link-scroll')
          .off click
          .on click, tab.tab_index, (event) ->
            scroll_to_tab(event.data)
            r 'magnific-popup', -> $.magnificPopup.close()
            false

        return

    # (3) Нажатие на кнопку удаления из списков
    $(document).on 'click', '.tpl-block-item-remove.tpl-link-favorite-item-remove A,.tpl-block-item-remove.tpl-link-compare-item-remove A', (event) ->
      event.preventDefault()
      $this = $(this)
      if $this.closest('.tpl-block-item-remove').hasClass('tpl-link-favorite-item-remove')
        $popup = $('#goodslist-favorite-remove-confirm')
      else
        $popup = $('#goodslist-compare-remove-confirm')

      $popup.find('INPUT[name=url]').val($this.attr('href'));
      $popup.find('INPUT[name=items]').val($this.closest('FORM').find('INPUT[name="items[]"]').val());
      r 'magnific-popup', ->
        $.magnificPopup.open
          items:
            src: $popup
          mainClass: 'tpl-block-mfp-animating'
          removalDelay: 200
          type: 'inline'
          closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'

      return false

    $(document).on 'click', '#goodslist-compare-remove-confirm BUTTON, #goodslist-favorite-remove-confirm BUTTON', (event) ->
      $this = $(this)
      items = $this.closest('.tpl-block-popup').find('INPUT[name=items]').val()
      if $this.hasClass('tpl-value-confirm')
        $.ajax
          url: $this.closest('.tpl-block-popup').find('INPUT[name=url]').val()
          dataType: 'html'
          success: on_footer_data_load
        $('.tpl-block-content .tpl-block-list-object').each ->
          $itemsInput = $(this).find('FORM INPUT[name="items[]"]')
          if $itemsInput.val() == items
            if $this.closest('.tpl-block-popup').is('#goodslist-compare-remove-confirm')
              $(this).find('FORM .tpl-link-compare-add').show().siblings('.tpl-link-compare-remove').hide()
            else
              $(this).find('FORM .tpl-link-favorite-add').show().siblings('.tpl-link-favorite-remove').hide()
          return true

      r 'magnific-popup', ->
        $.magnificPopup.close()

    return

  scroll_to_tab = (tab_index) ->
    tabSwitcher = $('.tpl-block-tabs--footer .tpl-block-tabs-switcher .tpl-block-tabs-tab').eq(tab_index)
    r 'magnific-popup', ->
      $.magnificPopup.close()
      tabSwitcher.triggerHandler('click')
      $('html, body').animate
        scrollTop: tabSwitcher.offset().top - 30

    return false


  ###
   * Добавление комментария
  ###
  on_main_content_load ->
     if window.location.hash == '#comment-added'
       tabSwitcher = $('.tpl-block-tabs-switcher .tpl-block-tabs-tab').eq(2).click()
       $('html, body').animate({ scrollTop: tabSwitcher.offset().top - 30 })

  ###
  * Добавление комментария
  ###
  $ ->
    $('.tpl-block-comments-form FORM').on 'submit', ->
      $this = $(this)
      $this.find('.tpl-block-error-info').remove()
      name = $this.find('INPUT[name="nc_comments_guest_name"]').val()
      email = $this.find('INPUT[name="nc_comments_guest_email"]').val()
      text = $this.find('TEXTAREA[name="nc_commentTextArea"]').val()
      redirect_url = $this.find('INPUT[name="redirect_url"]').val()
      regexp = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/

      if !name or !text
        error = 'Заполните поля Имя и Отзыв'
      if email and !regexp.test(email)
        error = 'Введите корректный E-mail'

      if error
        $this.find('BUTTON[type=submit]').closest('P').before('<div class="tpl-block-error-info">' + error + '</div>')
        return false

      $.post($this.attr('action'), $this.serializeArray(), (data) ->
        window.location.href = redirect_url
        window.location.reload()
      )
      return false
    return

  ###
   * Подписка
  ###
  $ ->
    $('FORM.tpl-block-subscribe').on 'submit', ->
      $this = $(this)
      $email = $this.find('INPUT[name="fields[Email]"]')
      $email.removeClass('tpl-state-error')
      regexp = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/

      if regexp.test($email.val())
        $.post($this.attr('action'), $this.serializeArray())

        r 'magnific-popup', ->
          $.magnificPopup.open
            items:
              src: '#subscriber-added'
            mainClass: 'tpl-block-mfp-animating'
            removalDelay: 200
            type: 'inline'
            closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'
      else
        $email.addClass('tpl-state-error').focus()

      return false
    return

  ###
   * Нажатие на звёздочки рейтинга
  ###
  on_main_content_load ->
    $(document).on 'click', '.tpl-block-rating > A', ->
      c = '.tpl-block-rating'
      $this = $(this)
      $block = $this.closest(c)
      index = $(c).index($block)

      responseHandler = (data) ->
        $data = $('<div/>').html(data)
        $block.replaceWith($data.find(c).eq(index))
        return

      $.post $this.attr('href'), responseHandler
      return false # return from the click handler

    return

  ###
   * Удаление товара из корзины
  ###
  $ ->
    $("#cart-item-remove-confirm").on "click", ->
      $.magnificPopup.close()
      button = $(this).data('button')
      form = button.closest('form')
      form.find("input[name='" + button.attr("name") + "']").val(0)
      form.submit()
      return
    return

  on_main_content_load ->
    $(document).on "click", ".tpl-link-cart-remove", (e) ->
      e.preventDefault()
      $('#cart-item-remove-confirm').data('button', $(this))
      r "magnific-popup", ->
        $.magnificPopup.open
          items:
            src: "#cart-item-remove"
          mainClass: "tpl-block-mfp-animating"
          removalDelay: 200
          type: "inline"
          closeMarkup: '<div class="tpl-block-mfp-close"><i class="icon-popup-close"></i></div>'
      return false
    return
  ###
   * Выпадающие списки
  ###
  close_all_dropdowns = ->
    $('.tpl-block-dropdown ul').slideUp(150)

  on_main_content_load ->
    $('.tpl-block-dropdown ul').click (e) -> e.stopPropagation()
    $('body').click -> close_all_dropdowns()
    $('.tpl-block-dropdown').click ->
        close_all_dropdowns()
        $($(this).data('target'))
            .css({'min-width':$(this).width()})
            .slideDown(200)
        return false


  ###
   * Фокус поля поиска при изменении значения в выпадающем списке «область поиска»
  ###
  $ ->
    search_block = $('header .tpl-block-search')
    search_block.find('select[name=area]').change () ->
      search_block.find('input[name=search_query]').focus()

  ###
   * Обработка главного меню навигации (отключить переход на текущую страницу)
  ###
  $ ->
    $current_link = $('body > header .tpl-block-menu > div a.tpl-state-current')
    href = window.location.href.replace(/^https?\:\/\/[^/]+/, '')
    if href == $current_link.attr('href')
      $current_link.css({pointerEvents: 'none'})

  ###
   * Amplify
  ###
  on_main_content_load ->
    r "amplify", ->
      if $("#nc_netshop_add_order_form").length
        store = ->
          $(this).on "change keyup", ->
            amplify.store this.name, $(this).val()
            return
          return

        restore = ->
          $this = $(this)
          stored_value = amplify.store(this.name)
          if typeof stored_value is 'string'
            $this.val stored_value if typeof stored_value is 'string'
            $this.trigger 'chosen:updated' if this.tagName is 'SELECT'
          return

        b1 = $(".tpl-block-order-customer").find(":input:not([name='f_SignUp']):not([type='checkbox'])").each store
        b2 = $(".tpl-block-order-address").find(":input:not([type='checkbox'])").each store

        if $(".tpl-block-auth .tpl-link-login").length
          b1.each restore
          b2.each restore

      return
    return


  ###
   * Инициализация основной части страницы при загрузке всей страницы
   * и при обновлении основной части страницы «аяксом»
  ###
  window.tpl_init_content = ->
    fn() for fn in main_content_load_handlers
    true

  ###
   *  Загрузка custom.js и вызов всех слушателей on_main_content_load при готовности
  ###
  r 'custom', ->
    $ ->
      tpl_init_content()
      return
    return

  # the end
  return

) window.jQuery, window
