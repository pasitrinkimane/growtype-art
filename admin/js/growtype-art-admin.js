(function ($) {
    $(document).ready(function () {
        $('.wrap .tab .tab-header').click(function () {
            $(this).closest('.tab').toggleClass('is-active')
        })

        /**
         * Lazy load videos on scroll
         */
        const initLazyVideos = () => {
            if ('IntersectionObserver' in window) {
                const videoObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const video = entry.target;
                            const source = video.querySelector('source');
                            if (source && source.dataset.src) {
                                source.src = source.dataset.src;
                                video.load();
                                video.classList.remove('lazy-video');
                            }
                            observer.unobserve(video);
                        }
                    });
                }, {
                    rootMargin: '200px 0px', // Start loading earlier
                    threshold: 0.1
                });

                document.querySelectorAll('video.lazy-video').forEach(video => {
                    videoObserver.observe(video);
                });
            } else {
                // Fallback for older browsers
                document.querySelectorAll('video.lazy-video').forEach(video => {
                    const source = video.querySelector('source');
                    if (source && source.dataset.src) {
                        source.src = source.dataset.src;
                        video.load();
                    }
                });
            }
        };

        // Initialize on load
        initLazyVideos();

        // Handle dynamically added content (AJAX)
        if ('MutationObserver' in window) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        initLazyVideos();
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
})(jQuery);
