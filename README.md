# assistant
My web-based music collection assistant

## features
- search
- browse
- find similar tracks (silly - based on metadata fields: initial_key, bpm, genre, year)
- rearrange mix by similarity (experimental)

## plans
- find inconsistencies / mismatches in track metadata ("Watermät" vs "Watermat", "The Disciples" vs. "Disciples", and so on)
- i18n
- ...?

## requirements
- PHP >= 5.6
- PHP MongoDB Extension (php5-mongo) >= 1.6.0
- MongoDB >= 3.x (2.x should works too)
- nginx / Apache

## Contributions
Contributions are greatly appreciated. Please fork this repository and open a pull request to add snippets, make grammar tweaks, etc.

## screenshots

#### Dashboard
![screenshot](http://i.imgur.com/iyTds3w.png "Dashboard")

#### Track info with similar tracks
![screenshot](http://i.imgur.com/vs80weq.png "Track")

#### Search for tracks
![screenshot](http://i.imgur.com/diZJn6a.png "Search")

#### Browse collection
![screenshot](http://i.imgur.com/lwRAgRz.png "Browse")