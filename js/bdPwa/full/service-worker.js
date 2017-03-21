'use strict';

//noinspection JSUnusedGlobalSymbols
let pwa = {

    onInstall: function (data, event) {
        var promises = [];

        //noinspection JSUnresolvedVariable
        for (let cachePrefix in data.preCaches) {
            //noinspection JSUnresolvedVariable
            if (!data.preCaches.hasOwnProperty(cachePrefix)) {
                continue;
            }

            let cacheName = pwa._getCacheName(data, cachePrefix);

            //noinspection JSUnresolvedVariable
            let preCacheFunction = function (urls) {
                return function (cache) {
                    //noinspection JSUnresolvedFunction
                    return cache.addAll(urls);
                };
            }(data.preCaches[cachePrefix]);

            promises.push(caches.open(cacheName).then(preCacheFunction));
        }

        if (promises.length == 0) {
            return;
        }

        //noinspection JSUnresolvedFunction
        event.waitUntil(Promise.all(promises));
    },

    onActivate: function (data, event) {
        if (data.debugMode) {
            console.log('SW activated', data.version);
        }
        let whiteList = [
            pwa._getCacheName(data, 'css'),
            pwa._getCacheName(data, 'js'),
            pwa._getCacheName(data, 'styles'),
            pwa._getCacheName(data, 'other'),
        ];
        //noinspection JSUnresolvedFunction
        event.waitUntil(caches.keys().then(function (cacheNames) {
            return Promise.all(cacheNames.map(function (cacheName) {
                if (whiteList.indexOf(cacheName) === -1) {
                    return caches.delete(cacheName);
                }
            }))
        }))
    },

    onFetch: function (data, event) {
        if (event.request.method !== 'GET') {
            if (data.debugMode) {
                console.log('SW fetch (bypass)', event.request.method, event.request.url);
            }
            return;
        }
        let url = event.request.url;
        //noinspection JSUnresolvedVariable
        if (url.indexOf(data.fullIndex) !== 0) {
            if (url.match(/https?:\/\/(ajax|code|fonts|maxcdn)\./)) {
                // also cache assets from popular CDN because they are often needed for proper offline rendering
                // especially jQuery and fonts
                pwa._onFetchCacheFirst(data, 'styles', event);
                return;
            }

            if (data.debugMode) {
                console.log('SW fetch (bypass)', event.request.url);
            }
            return;
        }

        //noinspection JSUnresolvedVariable
        let path = url.substr(data.fullIndex.length);

        if (path.indexOf('css.php?') === 0) {
            pwa._onFetchCacheFirst(data, 'css', event);
            return;
        }

        if (path.indexOf('js/') === 0) {
            pwa._onFetchCacheFirst(data, 'js', event);
            return;
        }

        if (path.indexOf('styles/') === 0) {
            pwa._onFetchCacheFirst(data, 'styles', event);
            return;
        }

        pwa.onFetchNetworkFirst(data, 'other', event);
    },

    _onFetchCacheFirst: function (data, cachePrefix, event) {
        if (data.debugMode) {
            console.log('SW fetch (cache first)', event.request.url);
        }

        //noinspection JSUnresolvedFunction
        event.respondWith(
            caches.open(pwa._getCacheName(data, cachePrefix))
                .then(function (cache) {
                    return cache.match(event.request)
                        .then(function (cachedResponse) {
                                if (cachedResponse) {
                                    return cachedResponse;
                                }

                                //noinspection JSCheckFunctionSignatures
                                return fetch(event.request).then(function (fetchedResponse) {
                                    cache.put(event.request, fetchedResponse.clone());
                                    return fetchedResponse;
                                });
                            }
                        );
                })
        );
    },

    onFetchNetworkFirst: function (data, cachePrefix, event) {
        if (data.debugMode) {
            console.log('SW fetch (network first)', event.request.url);
        }

        //noinspection JSUnresolvedFunction,JSCheckFunctionSignatures
        event.respondWith(
            fetch(event.request)
                .then(function (fetchResponse) {
                    let cacheName = pwa._getCacheName(data, cachePrefix);

                    return caches.open(cacheName)
                        .then(function (cache) {
                            cache.put(event.request, fetchResponse.clone());

                            //noinspection JSUnresolvedVariable
                            pwa._trimCache(cacheName, data.cacheNetworkFirstMaxItems);

                            return fetchResponse;
                        });
                })
                .catch(function () {
                    return caches.match(event.request);
                })
        );
    },

    _getCacheName: function (data, prefix) {
        //noinspection JSUnresolvedVariable
        var suffix = `data`;
        switch (prefix) {
            case 'css':
            case 'styles':
                //noinspection JSUnresolvedVariable
                suffix = `d${data.styleLastModifiedDate}`;
                break;
            case 'js':
                //noinspection JSUnresolvedVariable
                suffix = `js${data.jsLastUpdate}`;
                break;
        }

        //noinspection JSUnresolvedVariable
        return `${prefix}-${suffix}`;
    },

    _trimCache: function (cacheName, maxItems) {
        // https://gist.github.com/brandonrozek/0cf038df40a913fda655
        caches.open(cacheName)
            .then(function (cache) {
                cache.keys()
                    .then(function (keys) {
                        if (keys.length > maxItems) {
                            cache.delete(keys[0])
                                .then(pwa._trimCache(cacheName, maxItems));
                        }
                    });
            });
    }
};