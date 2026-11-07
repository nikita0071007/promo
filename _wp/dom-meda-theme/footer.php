</main>
<footer><div class="wrap"><div class="ft">
<a class="logo" href="<?php echo esc_url(dm_url('home','','')); ?>"><span>Дом Мёда<small>Натуральные продукты Алтая</small></span></a>
<div class="fl"><a href="<?php echo esc_url(dm_url('privacy','','')); ?>">Политика конфиденциальности</a><a href="<?php echo esc_url(dm_url('offer','','')); ?>">Оферта</a><a href="<?php echo esc_url(dm_url('payment','','')); ?>">Оплата</a><a href="<?php echo esc_url(dm_url('delivery','','')); ?>">Доставка</a><a href="<?php echo esc_url(dm_url('contacts','','')); ?>">Контакты</a></div>
<div class="soc"><a href="https://vk.ru/dommeda_altai" target="_blank" rel="noopener" aria-label="ВКонтакте"><svg viewBox="0 0 24 24"><path d="M12.8 17.5C6.9 17.5 3.5 13.4 3.4 6.6h3c.1 5 2.3 7.1 4 7.5V6.6h2.8v4.3c1.7-.2 3.5-2.1 4.1-4.3h2.8c-.5 2.7-2.4 4.6-3.8 5.4 1.4.7 3.5 2.4 4.3 5.5h-3.1c-.7-2.1-2.3-3.7-4.3-3.9v3.9z"/></svg></a><a href="https://t.me/D_Meda" target="_blank" rel="noopener" aria-label="Telegram"><svg viewBox="0 0 24 24"><path d="M21 4L3 11.2l5 1.8 1.9 6 2.8-3.6 4.6 3.4zM9 13l9-6.4-6.4 7.4"/></svg></a><a href="https://max.ru/u/f9LHodD0cOKHADhw9PlIaBZ7OcauWM72iyn0SKltly_5SMdbPXyii96VhCA" target="_blank" rel="noopener" aria-label="MAX"><svg viewBox="0 0 24 24"><rect x="2.5" y="3.5" width="19" height="17" rx="6.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M7 16V8.5l5 5.5 5-5.5V16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" stroke-linecap="round"/></svg></a></div></div>
<div class="copy"><span>© 2026 Дом Мёда. Все права защищены. Тестовая версия сайта.</span><a class="gm" href="https://gusevmade.ru/" target="_blank" rel="noopener" aria-label="Сделано студией GusevMade, перейти на сайт"><i>G</i>Сделано студией GusevMade</a></div></div></footer>
<div id="toast" role="status" aria-live="polite"></div><a href="#" id="up" aria-label="Наверх">↑</a>
<script>
(function(){var b=document.querySelector(".burger"),m=document.getElementById("menu"),u=document.getElementById("up");
if(b&&m)b.addEventListener("click",function(){var o=m.classList.toggle("open");b.setAttribute("aria-expanded",o?"true":"false")});
if(u){window.addEventListener("scroll",function(){u.classList.toggle("on",window.scrollY>600)});u.addEventListener("click",function(e){e.preventDefault();window.scrollTo({top:0,behavior:"smooth"})})}})();
</script>
<?php wp_footer(); ?>
</body>
</html>
