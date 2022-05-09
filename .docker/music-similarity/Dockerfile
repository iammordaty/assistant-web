FROM debian:8

ENV DEBIAN_FRONTEND=noninteractive
ENV LANG C.UTF-8
ENV TERM=xterm

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    build-essential \
    ca-certificates \
    cmake \
    git \
    libavcodec-dev \
    libavformat-dev \
    libavutil-dev \
    libeigen3-dev \
    locales \
    openssh-server;

RUN git clone https://github.com/iammordaty/musly.git /tmp/musly && \
    (cd /tmp/musly && \
    mkdir -p build && cd build && \
    cmake -DBUILD_STATIC=1 -DUSE_OPENMP=1 .. && \
    make && \
    make install && \
    rm -r /tmp/musly)

RUN apt-get autoremove && \
    apt-get clean

RUN echo "root:root" | chpasswd
RUN mkdir -p /var/run/sshd

VOLUME /collection
VOLUME /metadata

WORKDIR /metadata

EXPOSE 22

CMD [ "/usr/sbin/sshd", "-D", "-e", "-o", "LogLevel=info", "-o", "PermitRootLogin=yes" ]
