#!/usr/bin/env node
/**
 * Usage:
 * node scripts/khqr-generate.cjs --amount=0.10 --ref=ORD-xxx --account=nhun_pisal@bkrt --name="STARCAFE" --city="Phnom Penh" --currency=840
 */
const { BakongKHQR, IndividualInfo, khqrData } = require("bakong-khqr");

function arg(name, fallback = null) {
  const found = process.argv.find((x) => x.startsWith(`--${name}=`));
  if (!found) return fallback;
  return found.split("=").slice(1).join("=");
}

try {
  const amount = Number(arg("amount", "0"));
  const ref = String(arg("ref", ""));
  const account = String(arg("account", ""));
  const name = String(arg("name", "STARCAFE"));
  const city = String(arg("city", "Phnom Penh"));
  const currencyArg = arg("currency", "840"); // USD=840
  const currency = Number(currencyArg);

  if (!amount || !ref || !account) {
    console.log(JSON.stringify({ ok: false, message: "Missing amount/ref/account" }));
    process.exit(1);
  }

  // NOTE:
  // bakong-khqr examples show currency.khr, but you can pass numeric currency too (USD=840).
  const optionalData = {
    currency: Number.isFinite(currency) ? currency : (khqrData?.currency?.khr ?? 116),
    amount,
    billNumber: ref,
    storeLabel: name,
    terminalLabel: "STARCAFE",
  };

  // IMPORTANT FIX: must instantiate BakongKHQR and call instance method. :contentReference[oaicite:2]{index=2}
  const info = new IndividualInfo(account, optionalData.currency, name, city, optionalData);

  const khqr = new BakongKHQR();
  const res = khqr.generateIndividual(info);

  // res usually includes { qr, md5 }
  const qrString = res?.qr || res?.qrString || res?.data?.qr;

  console.log(
    JSON.stringify({
      ok: true,
      qr_string: qrString,
      md5: res?.md5 || null,
      raw: res,
    })
  );
} catch (e) {
  console.log(
    JSON.stringify({
      ok: false,
      message: "KHQR generate error",
      raw: String(e?.stack || e),
    })
  );
  process.exit(1);
}
