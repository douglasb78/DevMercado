<?php
// Reusable thumbnail carousel
// Expects $items = [ ['src'=>string, 'alt'=>string, 'title'=>string], ... ]
// Optional: set $thumb_size (px)
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

<style>
/* Carousel styles (scoped) */
.thumb-carousel { position: relative; max-width: 210px; }
.thumb-carousel .tc-viewport { overflow: hidden; }
.thumb-carousel .tc-track { display:flex; gap:6px; transition: transform .35s ease; will-change: transform; padding:4px 0; }
.thumb-carousel .tc-slide { width:48px; height:48px; border:1px solid #bbb; background:#fff; padding:0; flex:0 0 auto; cursor:pointer; border-radius:6px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.thumb-carousel .tc-slide img { width:100%; height:100%; object-fit:cover; transition: transform .18s ease; display:block; }
.thumb-carousel .tc-slide:hover img { transform: scale(1.12); }
.thumb-carousel .tc-nav { position:absolute; top:50%; transform:translateY(-50%); width:28px; height:28px; border-radius:50%; border:1px solid rgba(0,0,0,0.12); background:rgba(255,255,255,0.95); display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,0.08); z-index:2; }
.thumb-carousel .tc-prev { left:-6px; }
.thumb-carousel .tc-next { right:-6px; }
@media (max-width:700px){ .thumb-carousel { max-width:160px; } .thumb-carousel .tc-slide{width:42px;height:42px;} }
</style>

<script>
(function(){
  if (!window._thumbCarouselHelper) {
    window._thumbCarouselHelper = {
      inited: false,
      initAll: function() {
        document.querySelectorAll('.thumb-carousel[data-carousel]').forEach(function(root){
          if (root._tcInit) return; // already initialized
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
              // open image handled by inline onclick; center the clicked slide
              showIndex(i);
            });
          });

          // Wheel support: use mouse wheel (or touchpad) to navigate horizontally
          // Convert predominant vertical or horizontal delta into next/prev actions.
          var wheelTimeout = null;
          root.addEventListener('wheel', function(e){
            var dx = e.deltaX || 0;
            var dy = e.deltaY || 0;
            // determine predominant direction
            var primary = Math.abs(dx) > Math.abs(dy) ? dx : dy;
            // ignore tiny deltas
            if (Math.abs(primary) < 6) return;
            e.preventDefault();
            // pause auto cycling briefly
            try{ clearInterval(auto); }catch(_){}
            clearTimeout(wheelTimeout);
            if (primary > 0) showIndex(idx + 1); else showIndex(idx - 1);
            // resume auto-cycle after short idle
            wheelTimeout = setTimeout(function(){
              try{ auto = setInterval(function(){ showIndex(idx + 1); }, 3500); }catch(_){}
            }, 1500);
          }, { passive: false });

          // Auto-cycle
          var auto = setInterval(function(){ showIndex(idx + 1); }, 3500);
          root.addEventListener('mouseenter', function(){ clearInterval(auto); });
          root.addEventListener('mouseleave', function(){ auto = setInterval(function(){ showIndex(idx + 1); }, 3500); });

          // initial align
          setTimeout(function(){ showIndex(0); }, 50);
        });
      }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window._thumbCarouselHelper.initAll);
    else window._thumbCarouselHelper.initAll();
    } else {
      // already defined: just (re)initialize
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window._thumbCarouselHelper.initAll);
      else window._thumbCarouselHelper.initAll();
    }
  })();
</script>
