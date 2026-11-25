import assert from "node:assert/strict";
import { setTimeout as delay } from "node:timers/promises";

const base = new URL(process.argv[2] ?? "http://127.0.0.1:9413");
const get = (path, accept = "*/*") => fetch(new URL(path, base), { headers: { Accept: accept } });
const online = async () => {
  const end = Date.now() + 90_000;
  let failure;
  while (Date.now() < end) {
    try {
      const response = await get("/public-example/");
      if (response.ok && (await response.text()).includes("Markdown fixture text.")) return;
    } catch (error) { failure = error; }
    await delay(1000);
  }
  throw new Error("Fixture server did not initialize", { cause: failure });
};

await online();
const requested = await get("/public-example/", "text/html;q=0.6,text/markdown;q=0.9");
assert.match(requested.headers.get("content-type") ?? "", /^text\/markdown; charset=utf-8/i);
assert.match(requested.headers.get("vary") ?? "", /(?:^|,|\s)Accept(?:$|,|\s)/i);
assert.match(requested.headers.get("cache-control") ?? "", /no-store/i);
const markdown = await requested.text();
assert.match(markdown, /Markdown fixture text/);
assert.match(markdown, /^---\n/);
assert.doesNotMatch(markdown, /<!doctype html/i);

const alias = await get("/public-example.md");
assert.equal(alias.status, 200);
assert.match(alias.headers.get("content-type") ?? "", /^text\/markdown/i);
assert.match(await alias.text(), /Markdown fixture text/);
const page = await get("/public-page.md");
assert.match(page.headers.get("content-type") ?? "", /^text\/markdown/i);
assert.match(await page.text(), /Page fixture text/);

for (const accept of ["text/html", "text/markdown;q=0,*/*", "text/markdown;q=0.8,text/html;q=0.8"]) {
  const html = await get("/public-example/", accept);
  assert.match(html.headers.get("content-type") ?? "", /^text\/html/i);
  assert.match(html.headers.get("link") ?? "", /public-example\.md>; rel="alternate"; type="text\/markdown"/);
  assert.match(await html.text(), /<link rel="alternate" type="text\/markdown" href="[^"]*public-example\.md"/);
}

const front = await get("/");
assert.match(front.headers.get("link") ?? "", /\/\.md>; rel="alternate"/);
await front.text();
const frontAlias = await get("/.md");
assert.match(frontAlias.headers.get("content-type") ?? "", /^text\/markdown/i);
assert.match(await frontAlias.text(), /Static home fixture text/);

const protectedPage = await get("/protected-example.md", "text/markdown");
assert.doesNotMatch(protectedPage.headers.get("content-type") ?? "", /^text\/markdown/i);
assert.doesNotMatch(await protectedPage.text(), /Protected fixture text/);

const index = await get("/llms.txt");
assert.equal(index.status, 200);
const links = await index.text();
assert.match(links, /public-example\.md/);
assert.match(links, /public-page\.md/);
assert.doesNotMatch(links, /protected-example/);

const existing = await get("/llms.txt?fixture_existing=1");
assert.equal(await existing.text(), "Existing index response");
const ownedTemplate = await get("/llms.txt?fixture_template=1");
assert.equal(await ownedTemplate.text(), "Existing template response");
console.log("WordPress negotiation, aliases, cache headers, private content, and llms.txt checks passed.");
