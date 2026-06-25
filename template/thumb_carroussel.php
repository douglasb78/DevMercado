<?php

$thumb_size = $thumb_size ?? 48;
$id = 'thumbc_' . bin2hex(random_bytes(6));
?>
<div class="thumb-carousel" data-carousel id="<?= $id ?>" onclick="event.stopPropagation()">
  <button type="button" class="tc-nav tc-prev" aria-label="Anterior" tabindex="-1">‹</button>
  <div class="tc-viewport">
    <div class="tc-track">
      <?php foreach ($items as $it):
        $src = $it['src'] ?? '';
        $alt = $it['alt'] ?? '';
        $title = $it['title'] ?? $alt;
      ?>
        <button type="button" class="thumb-button tc-slide" data-src="<?= htmlspecialchars($src) ?>" data-title="<?= htmlspecialchars($title) ?>" onclick="abrirImagem(this.dataset.src, this.dataset.title)">
          <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($alt) ?>">
        </button>
      <?php endforeach; ?>
    </div>
  </div>
  <button type="button" class="tc-nav tc-next" aria-label="Próximo" tabindex="-1">›</button>
</div>


<link rel="stylesheet" href="/css/components/thumb_carroussel.css">

<script>
(function(){
  if (!window._thumbCarouselHelper) {
    window._thumbCarouselHelper = {
      inited: false,
      initAll: function() {
        document.querySelectorAll('.thumb-carousel[data-carousel]').forEach(function(root){
          if (root._tcInit) return;
          root._tcInit = true;

          var viewport = root.querySelector('.tc-viewport');
          var track = root.querySelector('.tc-track');
          var slides = Array.from(root.querySelectorAll('.tc-slide'));
          var prev = root.querySelector('.tc-prev');
          var next = root.querySelector('.tc-next');
          var idx = 0;

          function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

          function showIndex(i) {
            if (slides.length === 0) return;
            idx = ((i % slides.length) + slides.length) % slides.length;
            var slide = slides[idx];
            var slideLeft = slide.offsetLeft;
            var slideCenterOffset = (viewport.clientWidth - slide.clientWidth) / 2;
            var desired = slideLeft - slideCenterOffset;
            var maxShift = Math.max(0, track.scrollWidth - viewport.clientWidth);
            desired = clamp(desired, 0, maxShift);
            track.style.transform = 'translateX(' + (-desired) + 'px)';
          }

          prev.addEventListener('click', function(e){ e.stopPropagation(); showIndex(idx - 1); });
          next.addEventListener('click', function(e){ e.stopPropagation(); showIndex(idx + 1); });

          slides.forEach(function(s, i){
            s.addEventListener('click', function(e){
              showIndex(i);
            });
          });

          var wheelTimeout = null;
          root.addEventListener('wheel', function(e){
            var dx = e.deltaX * -1 || 0;
            var dy = e.deltaY * -1 || 0;
            var primary = Math.abs(dx) > Math.abs(dy) ? dx : dy;
            if (Math.abs(primary) < 6) return;
            e.preventDefault();
            try{ clearInterval(auto); }catch(_){}
            clearTimeout(wheelTimeout);
            if (primary > 0) showIndex(idx + 1); else showIndex(idx - 1);
            wheelTimeout = setTimeout(function(){
              try{ auto = setInterval(function(){ showIndex(idx + 1); }, 3500); }catch(_){}
            }, 1500);
          }, { passive: false });

          var auto = setInterval(function(){ showIndex(idx + 1); }, 3500);
          root.addEventListener('mouseenter', function(){ clearInterval(auto); });
          root.addEventListener('mouseleave', function(){ auto = setInterval(function(){ showIndex(idx + 1); }, 3500); });

          setTimeout(function(){ showIndex(0); }, 50);
        });
      }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window._thumbCarouselHelper.initAll);
    else window._thumbCarouselHelper.initAll();
    } else {
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window._thumbCarouselHelper.initAll);
      else window._thumbCarouselHelper.initAll();
    }
  })();
</script>
