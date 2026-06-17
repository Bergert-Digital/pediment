import { isAccepted, splitDataUri, selectFiles } from "../images";

const file = (type: string) => new File(["x"], "f", { type });

describe("isAccepted", () => {
  it("accepts the four supported image types", () => {
    expect(isAccepted("image/png")).toBe(true);
    expect(isAccepted("image/jpeg")).toBe(true);
    expect(isAccepted("image/gif")).toBe(true);
    expect(isAccepted("image/webp")).toBe(true);
  });
  it("rejects unsupported types", () => {
    expect(isAccepted("application/pdf")).toBe(false);
    expect(isAccepted("text/plain")).toBe(false);
    expect(isAccepted("image/svg+xml")).toBe(false);
  });
});

describe("splitDataUri", () => {
  it("splits media type and base64 payload, dropping the prefix", () => {
    expect(splitDataUri("data:image/png;base64,AAAB")).toEqual({
      media_type: "image/png",
      data: "AAAB",
    });
  });
  it("throws on a non-data URI", () => {
    expect(() => splitDataUri("http://example.com/x.png")).toThrow();
  });
});

describe("selectFiles", () => {
  it("drops non-image files and flags rejection", () => {
    const { accepted, rejected } = selectFiles(
      [file("image/png"), file("application/pdf")],
      5,
    );
    expect(accepted).toHaveLength(1);
    expect(rejected).toBe(true);
  });
  it("caps at the remaining room and flags rejection", () => {
    const { accepted, rejected } = selectFiles(
      [file("image/png"), file("image/jpeg"), file("image/gif")],
      2,
    );
    expect(accepted).toHaveLength(2);
    expect(rejected).toBe(true);
  });
  it("accepts everything when within room and all images", () => {
    const { accepted, rejected } = selectFiles([file("image/png")], 5);
    expect(accepted).toHaveLength(1);
    expect(rejected).toBe(false);
  });
});
