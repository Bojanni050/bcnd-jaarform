// Sets webpack publicPath at runtime so lazy chunks load from the plugin URL in WordPress.
if (typeof window !== "undefined" && window.BCND && window.BCND.appBase) {
  // eslint-disable-next-line no-undef, camelcase
  __webpack_public_path__ = window.BCND.appBase;
}
