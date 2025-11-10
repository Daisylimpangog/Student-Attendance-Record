// Sanitize iframes that use the unsafe combination: allow-scripts + allow-same-origin
// Having both essentially disables the sandbox; remove allow-same-origin when found.
(function(){
	function sanitizeIframe(iframe){
		try {
			const s = iframe.getAttribute('sandbox') || '';
			if (!s) return false;
			// Avoid sanitizing same-origin iframes (they may rely on same-origin privileges)
			// We only sanitize cross-origin frames which are more likely to be untrusted widgets.
			try {
				const src = iframe.getAttribute('src') || '';
				if (src) {
					const a = document.createElement('a');
					a.href = src;
					const iframeOrigin = a.protocol + '//' + a.host;
					const myOrigin = window.location.protocol + '//' + window.location.host;
					if (iframeOrigin === myOrigin) {
						return false; // skip sanitizing same-origin frames
					}
				}
			} catch (e) {
				// ignore parse errors and proceed
			}
			const tokens = s.split(/\s+/).filter(Boolean);
			const hasScripts = tokens.includes('allow-scripts');
			const hasSameOrigin = tokens.includes('allow-same-origin');
			if (hasScripts && hasSameOrigin) {
				// Remove allow-same-origin to preserve sandboxing while still allowing scripts
				const newTokens = tokens.filter(t => t !== 'allow-same-origin');
				const newVal = newTokens.join(' ');
				iframe.setAttribute('sandbox', newVal);
				console.warn('Sanitized iframe sandbox by removing allow-same-origin to avoid sandbox escape:', iframe.src || iframe);
				return true;
			}
		} catch (err) {
			console.error('Failed to sanitize iframe', err, iframe);
		}
		return false;
	}

	function sanitizeAll(){
		const iframes = document.querySelectorAll('iframe');
		let fixed = 0;
		iframes.forEach(f => { if (sanitizeIframe(f)) fixed++; });
		if (fixed) console.info('Iframe sanitizer: fixed', fixed, 'iframe(s)');
	}

	// Run early (if DOM already parsed) and on DOMContentLoaded otherwise
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		sanitizeAll();
	} else {
		document.addEventListener('DOMContentLoaded', sanitizeAll);
	}

	// Observe additions to the DOM for dynamic iframes
	try {
		const mo = new MutationObserver(muts => {
			muts.forEach(m => {
				m.addedNodes && m.addedNodes.forEach(node => {
					if (node && node.nodeType === 1) {
						if (node.tagName === 'IFRAME') sanitizeIframe(node);
						// also check descendants
						node.querySelectorAll && node.querySelectorAll('iframe').forEach(sanitizeIframe);
					}
				});
			});
		});
		mo.observe(document.documentElement || document.body, { childList: true, subtree: true });
	} catch (err) {
		// ignore if MutationObserver isn't available
	}

	// Expose helper for debugging
	window.__sanitizeIframes = sanitizeAll;
})();

// Convert marked buttons to icon-only buttons while preserving accessibility.
(function(){
	function iconizeButtons(){
		const btns = document.querySelectorAll('button[data-icon], a[data-icon]');
		btns.forEach(b => {
			if (b.__iconized) return;
			const icon = b.getAttribute('data-icon');
			if (!icon) return;
			const only = b.getAttribute('data-icon-only') === 'true';
			const text = (b.textContent || '').trim();
			// create icon element
			const i = document.createElement('i');
			i.className = 'bi ' + icon + ' me-1';

			if (only) {
				// icon-only: replace contents but keep accessible label
				while (b.firstChild) b.removeChild(b.firstChild);
				b.appendChild(i);
				if (text) {
					b.setAttribute('title', text);
					b.setAttribute('aria-label', text);
				}
				b.classList.add('btn-icon');
			} else {
				// prepend icon and keep text (improves discoverability)
				// Only prepend if the first child isn't already an icon
				const first = b.firstElementChild;
				if (!(first && first.tagName === 'I' && first.classList.contains('bi'))) {
					b.insertBefore(i, b.firstChild);
				}
			}

			b.__iconized = true;
		});
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') iconizeButtons();
	else document.addEventListener('DOMContentLoaded', iconizeButtons);
	// expose for manual runs
	window.__iconizeButtons = iconizeButtons;
})();

// Inject minimal styles for icon-only buttons
(function(){
		const css = `
		.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; padding: 0; }
		.btn-icon i { font-size: 1.1rem; }
		`;
		const s = document.createElement('style');
		s.appendChild(document.createTextNode(css));
		document.head && document.head.appendChild(s);
})();
