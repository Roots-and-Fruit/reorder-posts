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
