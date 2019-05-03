FROM {base}

RUN mkdir /workspace
RUN mkdir /local
RUN mkdir /sbs

WORKDIR /workspace

COPY ./input/* /workspace/
COPY ./build /sbs/build
COPY ./move-output-when-needed /sbs/move-output-when-needed
COPY ./build-output-hash /sbs/build-output-hash
COPY ./run /sbs/run

RUN chmod +x /sbs/*
RUN /sbs/build
RUN /sbs/build-output-hash

ENTRYPOINT ["/sbs/move-output-when-needed"]

CMD ["/sbs/run"]
