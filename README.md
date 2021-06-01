# assistant
My web-based music collection assistant (under high development and BC changes)

## features
- search
- browse
- find similar tracks (based on track similarity* and metadata fields: initial_key, bpm, genre, year)
- rearrange mix by similarity (based on track similarity module)

\* Thanks to [Musly - Music Similarity Library](https://github.com/dominikschnitzer/musly)

## plans
- find inconsistencies / mismatches in track metadata ("Watermät" vs "Watermat", "The Disciples" vs. "Disciples", and so on)
- i18n
- custom tags and links to other tracks in the track view
- integrate Indexer with [Essentia](https://github.com/MTG/essentia) (which is "library for audio and music analysis, description and synthesis")
- opt-out of a backend written in go
- ...?

## requirements
- PHP >= 8
- MongoDB >= 4.x
- nginx / Apache

## how to start?

See [docker files](https://github.com/iammordaty/docker-files/tree/master/assistant) for more info (sorry).

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
