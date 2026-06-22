import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity"
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
(module) {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ }

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	if (!(moduleId in __webpack_modules__)) {
/******/ 		delete __webpack_module_cache__[moduleId];
/******/ 		var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 		e.code = 'MODULE_NOT_FOUND';
/******/ 		throw e;
/******/ 	}
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!***********************************!*\
  !*** ./src/blocks/slider/view.ts ***!
  \***********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");

const wrap = (i, n) => n > 0 ? (i % n + n) % n : 0;

/**
 * Apply the active index to the DOM imperatively, by document order. DOM order
 * is the single source of truth for slide/dot position, so no per-slide context
 * is needed.
 *
 * @param {HTMLElement} root   The slider root element.
 * @param {number}      active The zero-based index of the active slide.
 */
const paint = (root, active) => {
  const slides = root.querySelectorAll('.starter-slide');
  const dots = root.querySelectorAll('.starter-slider__dot');
  slides.forEach((slide, i) => {
    const on = i === active;
    slide.classList.toggle('is-active', on);
    slide.setAttribute('aria-hidden', on ? 'false' : 'true');
    slide.toggleAttribute('inert', !on);
  });
  dots.forEach((dot, i) => {
    const on = i === active;
    dot.classList.toggle('is-current', on);
    if (on) {
      dot.setAttribute('aria-current', 'true');
    } else {
      dot.removeAttribute('aria-current');
    }
  });

  // Slide the rail horizontally so the active slide fills the card.
  const rail = root.querySelector('.starter-slider__rail');
  if (rail) {
    rail.style.transform = `translateX(${-active * 100}%)`;
  }
  const live = root.querySelector('.starter-slider__live');
  if (live) {
    live.textContent = `${active + 1} / ${slides.length}`;
  }
};
const {
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('pediment/slider', {
  actions: {
    next() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      ctx.active = wrap(ctx.active + 1, ctx.count);
    },
    prev() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      ctx.active = wrap(ctx.active - 1, ctx.count);
    },
    goTo() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const idx = Number(ref?.getAttribute('data-index') ?? 0);
      ctx.active = wrap(idx, ctx.count);
    },
    onKeydown(event) {
      if (event.key === 'ArrowRight') {
        event.preventDefault();
        actions.next();
      } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        actions.prev();
      }
    }
  },
  callbacks: {
    init() {
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      if (!ref) {
        return;
      }
      ref.classList.add('is-enhanced');
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      paint(ref, ctx.active);
    },
    render() {
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      if (!ref) {
        return;
      }
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      paint(ref, ctx.active);
    }
  }
});
})();


//# sourceMappingURL=view.js.map