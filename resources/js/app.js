import './bootstrap';
import './prevent-double-submit';
import Alpine from 'alpinejs';

// Register every file in resources/images so Vite::asset() can find it
import.meta.glob(['../images/**']);

window.Alpine = Alpine;

Alpine.start();