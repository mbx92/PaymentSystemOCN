import { onBeforeUnmount, onMounted } from 'vue';

export function useLandingTracking() {
  const startedAt = Date.now();
  let visibleStartedAt = document.visibilityState === 'visible' ? Date.now() : null;
  let visibleMs = 0;
  let maxScrollPercent = 0;
  let hasSentExit = false;

  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const updateScroll = () => {
    const root = document.documentElement;
    const scrollable = Math.max(root.scrollHeight - window.innerHeight, 0);
    const percent = scrollable === 0 ? 100 : Math.min(100, Math.max(0, (window.scrollY / scrollable) * 100));
    maxScrollPercent = Math.max(maxScrollPercent, Number(percent.toFixed(2)));
  };

  const updateVisibility = () => {
    if (document.visibilityState === 'visible') {
      if (visibleStartedAt === null) {
        visibleStartedAt = Date.now();
      }
      return;
    }

    if (visibleStartedAt !== null) {
      visibleMs += Date.now() - visibleStartedAt;
      visibleStartedAt = null;
    }
  };

  const postEvent = (eventName, eventMeta = {}, useBeacon = false) => {
    const payload = JSON.stringify({
      event_name: eventName,
      event_meta: eventMeta,
    });

    if (useBeacon && navigator.sendBeacon) {
      const blob = new Blob([payload], { type: 'application/json' });
      navigator.sendBeacon(route('landing.track'), blob);
      return;
    }

    fetch(route('landing.track'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      keepalive: useBeacon,
      body: payload,
    }).catch(() => {});
  };

  const trackCtaClick = (ctaKind, ctaText, ctaUrl) => {
    postEvent('cta_click', {
      cta_kind: ctaKind,
      cta_text: ctaText,
      cta_url: ctaUrl,
    });
  };

  const trackExit = () => {
    if (hasSentExit) return;
    hasSentExit = true;
    updateVisibility();
    updateScroll();

    postEvent('page_exit', {
      active_ms: Date.now() - startedAt,
      visible_ms: visibleMs,
      max_scroll_percent: maxScrollPercent,
    }, true);
  };

  onMounted(() => {
    window.addEventListener('scroll', updateScroll, { passive: true });
    document.addEventListener('visibilitychange', updateVisibility);
    window.addEventListener('pagehide', trackExit);
    updateScroll();
  });

  onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateScroll);
    document.removeEventListener('visibilitychange', updateVisibility);
    window.removeEventListener('pagehide', trackExit);
  });

  return {
    trackCtaClick,
  };
}
