// 传输层抽象：解析器不直接发网络请求，统一走注入的 transport。
// 手机版注入 CapacitorHttp 适配器（原生网络层，无 CORS）；电脑版将来注入 Node fetch 适配器。
//
// transport.request({ url, method, headers, body, timeoutMs })
//   => { status: number, data: string|object, headers: Object(键小写), url: string(重定向后的最终地址) }

let active = null;

export function setTransport(transport) {
  active = transport;
}

export function getTransport() {
  if (!active) {
    throw new Error('网络传输层未初始化：请先调用 setTransport 注入 CapacitorHttp 或 Node fetch 适配器');
  }
  return active;
}
