# assistant
My web-based music collection assistant (under high development and BC changes)

## features
- search and browse collection
- find similar tracks (based on track similarity* and metadata fields: initial_key, bpm, genre, year)
- calculate track tempo (bpm), musical key and audio features**
- rearrange mix by similarity (based on track similarity module)

\* Thanks to [Musly - Music Similarity Library](https://github.com/dominikschnitzer/musly)  
\** Thanks to [Essentia](https://github.com/MTG/essentia) via [music extractor](https://github.com/MTG/essentia/blob/master/doc/sphinxdoc/streaming_extractor_music.rst)


## plans
- find inconsistencies / mismatches in track metadata ("Watermät" vs "Watermat", "The Disciples" vs. "Disciples", and so on)
- i18n
- custom tags and links to other tracks in the track view
- ...?

## requirements
- PHP >= 8.1
- MongoDB >= 4.x
- nginx

## how to start?

See [docker-compose.yml](https://github.com/iammordaty/assistant-web/blob/master/docker-compose.yml) 
and [.env.example](https://github.com/iammordaty/assistant-web/blob/master/.env.example) for more info (sorry).

## contributions
Contributions are greatly appreciated. Feel free to open PR.

## screenshots

#### dashboard
![screenshot](http://i.imgur.com/iyTds3w.png "Dashboard")

#### track info with similar tracks
![screenshot](http://i.imgur.com/vs80weq.png "Track")

#### search for tracks
![screenshot](http://i.imgur.com/diZJn6a.png "Search")

#### browse collection
![screenshot](http://i.imgur.com/lwRAgRz.png "Browse")
