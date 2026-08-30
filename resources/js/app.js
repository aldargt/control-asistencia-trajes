import './bootstrap';

const navigation = document.querySelector('#main-navigation');
const toggle = document.querySelector('#navigation-toggle');
const backdrop = document.querySelector('#navigation-backdrop');

const setNavigationOpen = (isOpen) => {
    if (! navigation || ! toggle || ! backdrop) return;

    navigation.classList.toggle('hidden', ! isOpen);
    navigation.classList.toggle('flex', isOpen);
    backdrop.classList.toggle('hidden', ! isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
};

toggle?.addEventListener('click', () => {
    setNavigationOpen(toggle.getAttribute('aria-expanded') !== 'true');
});

backdrop?.addEventListener('click', () => setNavigationOpen(false));
