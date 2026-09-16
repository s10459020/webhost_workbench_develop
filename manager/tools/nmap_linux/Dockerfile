FROM alpine:latest

# 1. 確保所有靜態庫都到位
RUN apk add --no-cache \
    git make g++ autoconf automake flex bison \
    openssl-dev openssl-libs-static \
    libpcap-dev linux-headers zlib-dev zlib-static pkgconfig

RUN git clone --depth 1 https://github.com/nmap/nmap.git /nmap-source
WORKDIR /nmap-source

# 2. 正常配置
RUN ./configure \
    --with-libpcap=included \
    --with-libpcre=included \
    --with-libz=system \
    --without-libssh2 \
    --without-nping \
    --without-ncat \
    --with-openssl=included \
    --without-nmap-update \
    --without-zenmap

# 3. 關鍵改進：強迫 Makefile 使用全靜態連結
# 我們直接修改產出的 Makefile，將 LDFLAGS 注入 -static
# 並且把連結順序交給 Nmap 自己處理
RUN sed -i 's/^LDFLAGS = /LDFLAGS = -static -static-libgcc -static-libstdc++ /' Makefile && \
    make -j$(nproc) && \
    strip nmap