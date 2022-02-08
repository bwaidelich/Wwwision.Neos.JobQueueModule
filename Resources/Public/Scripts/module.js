window.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('[data-toggle]').forEach(element => {
		element.addEventListener('click', event => {
			event.preventDefault();
			event.target.classList.toggle('fa-chevron-down');
			event.target.classList.toggle('fa-chevron-up');
			document.querySelector('.' + event.target.dataset.toggle).classList.toggle('neos-hide');
		})
	});
})
