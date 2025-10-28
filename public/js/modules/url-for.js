export default (routeName, params = {}) => {
    switch (routeName) {
        case 'track.track.index':
            if (!params.guid) {
                throw new Error('Missing "guid" parameter for route "track.track.index"');
            }

            return `/track/${encodeURIComponent(params.guid)}`;

        case 'search.advanced.index':
            const query = new URLSearchParams(params).toString();
            return `/search/advanced${query ? `?${query}` : ''}`;

        default:
            throw new Error(`Unknown route: ${routeName}`);
    }
}
