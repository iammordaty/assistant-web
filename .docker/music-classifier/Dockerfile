FROM iammordaty/essentia:2021-08-25

ENV TERM=xterm
ENV DEBIAN_FRONTEND=noninteractive

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
