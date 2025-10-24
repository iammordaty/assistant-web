FROM iammordaty/essentia:2022-11-18

ENV TERM=xterm
ENV DEBIAN_FRONTEND=noninteractive

RUN sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list && \
    sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list && \
    sed -i '/jessie-updates/d' /etc/apt/sources.list && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until && \
    echo 'Acquire::AllowInsecureRepositories "true";' >> /etc/apt/apt.conf.d/99no-check-valid-until && \
    echo 'APT::Get::AllowUnauthenticated "true";' >> /etc/apt/apt.conf.d/99no-check-valid-until

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    openssh-server;

RUN apt-get autoremove && \
    apt-get clean

RUN echo "root:root" | chpasswd
RUN mkdir -p /var/run/sshd

VOLUME /collection
VOLUME /metadata

EXPOSE 22

CMD [ "/usr/sbin/sshd", "-D", "-e", "-o", "LogLevel=info", "-o", "PermitRootLogin=yes" ]
