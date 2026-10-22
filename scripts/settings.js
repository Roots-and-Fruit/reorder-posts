(function () {
	document.querySelectorAll('.mn-rf-toggle__input').forEach(function (input) {
		var state = input.parentElement.querySelector('.mn-rf-toggle__state');
		if (!state) {
			return;
		}
		input.addEventListener('change', function () {
			state.textContent = input.checked ? state.getAttribute('data-enabled') : state.getAttribute('data-disabled');
		});
	});

	function upgradeLegacyCodeBlocks() {
		document.querySelectorAll('.mn-rf-settings blockquote > pre > code').forEach(function (code) {
			var blockquote = code.closest('blockquote');
			if (!blockquote || blockquote.closest('.rf-code')) {
				return;
			}
			var wrapper = document.createElement('div');
			wrapper.className = 'rf-code';
			wrapper.innerHTML =
				'<div class="rf-code__chrome">' +
					'<div class="rf-code__dots" aria-hidden="true"><span></span><span></span><span></span></div>' +
					'<button type="button" class="rf-code__copy" aria-label="Copy code">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">' +
							'<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6A2.25 2.25 0 0014.25 3.75H6A2.25 2.25 0 003.75 6v8.25A2.25 2.25 0 006 16.5h2.25m8.25-8.25H18A2.25 2.25 0 0120.25 10.5V18A2.25 2.25 0 0118 20.25h-7.5A2.25 2.25 0 018.25 18v-1.5m8.25-8.25h-6A2.25 2.25 0 008.25 10.5v6"/>' +
						'</svg>' +
					'</button>' +
				'</div>' +
				'<pre class="rf-code__body"><code></code></pre>' +
				'<span class="rf-code__lang">PHP</span>';
			wrapper.querySelector('code').textContent = (code.textContent || '').trim();
			blockquote.replaceWith(wrapper);
		});
	}

	function bindCodeCopy() {
		document.querySelectorAll('.mn-rf-settings .rf-code__copy').forEach(function (button) {
			if (button.dataset.bound) {
				return;
			}
			button.dataset.bound = '1';
			button.addEventListener('click', function () {
				var block = button.closest('.rf-code');
				var code = block ? block.querySelector('.rf-code__body code') : null;
				if (!code) {
					return;
				}
				var text = code.textContent || '';
				var done = function () {
					button.setAttribute('data-copied', '1');
					clearTimeout(button._rfCopyTimer);
					button._rfCopyTimer = setTimeout(function () {
						button.removeAttribute('data-copied');
					}, 1600);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done).catch(function () {
						fallbackCopy(text, done);
					});
					return;
				}
				fallbackCopy(text, done);
			});
		});
	}

	function fallbackCopy(text, done) {
		var area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', '');
		area.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;border:0;padding:0;';
		document.body.appendChild(area);
		area.select();
		try {
			document.execCommand('copy');
			done();
		} catch (e) {
			// Ignore.
		}
		document.body.removeChild(area);
	}

	upgradeLegacyCodeBlocks();
	bindCodeCopy();

	var brand = document.querySelector('.mn-rf-footer__brand');
	var about = document.getElementById('mn-rf-about');
	if (!brand || !about) {
		return;
	}

	function setAbout(open) {
		about.hidden = !open;
		brand.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	brand.addEventListener('click', function () {
		setAbout(about.hidden);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !about.hidden) {
			setAbout(false);
			brand.focus();
		}
	});
}());
