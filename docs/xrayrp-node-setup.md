# SSPanel-Metron 对接 XrayRP：Hysteria2 / Tuic / AnyTLS 节点标准设置

本文档说明本次新增的三种节点在 `ss_node` 表中的标准填写方式（重点是 `sort` 与 `server` 字段），用于和 XrayRP 实例对接。

## 1) 节点类型（sort）约定

- `16` = `Hysteria2`
- `17` = `Tuic`
- `18` = `AnyTLS`

> 管理后台创建/编辑节点时，直接选择对应类型即可。

---

## 2) server 字段统一格式

三种新协议统一使用：

```text
主机名或IP;key=value|key=value|...
```

- 分号 `;` 前：服务器地址（例如 `node1.example.com`）
- 分号后：扩展参数，`|` 分隔

---

## 3) Hysteria2 标准设置（sort=16）

### 推荐 server 示例

```text
hy2.example.com;port=443|sni=hy2.example.com|alpn=h3|insecure=0|obfs=salamander|obfs_password=your-obfs-secret|upmbps=100|downmbps=300
```

### 参数说明

- `port`：端口，默认 `443`
- `sni`：TLS SNI（建议与证书域名一致）
- `alpn`：逗号分隔（如 `h3`）
- `insecure`：`0`/`1`，是否跳过证书验证
- `obfs`：混淆类型（如 `salamander`）
- `obfs_password`：混淆密码
- `upmbps` / `downmbps`：上下行速率声明

### 凭据映射

- 密码使用用户 UUID（`$user->getUuid()`）下发。

---

## 4) Tuic 标准设置（sort=17）

### 推荐 server 示例

```text
tuic.example.com;port=443|sni=tuic.example.com|alpn=h3|insecure=0|congestion_control=bbr|udp_relay_mode=native|zero_rtt_handshake=0
```

### 参数说明

- `port`：端口，默认 `443`
- `sni`：TLS SNI
- `alpn`：如 `h3`
- `insecure`：`0`/`1`
- `congestion_control`：拥塞控制（推荐 `bbr`）
- `udp_relay_mode`：UDP relay 模式（推荐 `native`）
- `zero_rtt_handshake`：`0`/`1`

### 凭据映射

- `uuid` 使用用户 UUID（`$user->getUuid()`）
- `password` 使用用户连接密码（`$user->passwd`）

---

## 5) AnyTLS 标准设置（sort=18）

### 推荐 server 示例

```text
anytls.example.com;port=443|sni=anytls.example.com|alpn=h2,http/1.1|insecure=0
```

### 参数说明

- `port`：端口，默认 `443`
- `sni`：TLS SNI
- `alpn`：如 `h2,http/1.1`
- `insecure`：`0`/`1`

### 凭据映射

- 密码使用用户 UUID（`$user->getUuid()`）下发。

---

## 6) 订阅下发覆盖范围

新增协议已纳入：

- 通用 `?sub=3`（V2聚合）
- `list=v2rayn`（URI）
- `clash` / `clashmeta`
- `singbox`

如要与 XrayRP 一致，请确保 XrayRP 端监听端口、证书、SNI、ALPN 与上面 `server` 参数一致。

---

## 7) ClashMeta xhttp 下发规范（mihomo）

已按 mihomo 的 xhttp 结构补充 ClashMeta 订阅下发（仅 VLESS 网络类型）。

当 VLESS 节点的 `net=xhttp` 时，会生成：

```yaml
network: xhttp
xhttp-opts:
  path: /xxx
  host: example.com
  mode: auto|stream-one|stream-up|packet-up
```

可选：

- `no-grpc-header`（由 `no_grpc_header=1/true` 控制）

服务端参数建议通过 VLESS 节点 `server` 扩展参数传入，例如：

```text
vless.example.com;443;0;xhttp;none;host=vless.example.com|path=/xhttp|mode=stream-up|sni=vless.example.com|security=tls
```
