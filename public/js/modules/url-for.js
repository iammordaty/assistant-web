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

        case 'mix.list.index':
            return '/mix/list';

        case 'mix.mix.view':
            if (!params.guid) {
                throw new Error('Missing "guid" parameter for route "mix.mix.view"');
            }

            return `/mix/${encodeURIComponent(params.guid)}`;

        case 'mix.mix.save-mix':
            return params.guid ? `/mix/${encodeURIComponent(params.guid)}` : '/mix';

        case 'mix.mix.save-attempt':
            if (!params.guid) {
                throw new Error('Missing "guid" parameter for route "mix.mix.save-attempt"');
            }

            return `/mix/${encodeURIComponent(params.guid)}/attempt`;

        default:
            throw new Error(`Unknown route: ${routeName}`);
    }
}
