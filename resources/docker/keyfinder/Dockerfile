FROM ubuntu:15.10

ENV DEBIAN_FRONTEND noninteractive

# sources

RUN apt-get clean && apt-get update -y

# installation

RUN apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    vim \
    git \
    build-essential \
    qt5-default \ 
    libfftw3-dev \
    libavutil-ffmpeg-dev \
    libavresample-ffmpeg-dev \
    libavcodec-ffmpeg-dev \
    libavformat-ffmpeg-dev
   
RUN git clone https://github.com/ibsh/libKeyFinder.git keyfinder && \
    (cd keyfinder && \
    curl -O https://raw.githubusercontent.com/EvanPurkhiser/libKeyFinder/fixup/install-target/LibKeyFinder.pro && \
    qmake && \
    make && \
    make install)

RUN git clone https://github.com/EvanPurkhiser/keyfinder-cli.git && \
    (cd keyfinder-cli  && \
    make && \
    make install)

VOLUME ["/collection"]

WORKDIR /collection

ENTRYPOINT ["/usr/local/bin/keyfinder-cli", "-n", "camelot"]
