#!/usr/bin/env python3
"""Image -> WebP optimizer with a simple desktop UI.

Open a folder, click an image to convert it (status becomes "Done"),
or press "Convert All". Output goes to a "webp" subfolder; originals
are never touched.

Requires: pip install Pillow
"""
import os
import threading
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

from PIL import Image, ImageOps

EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".tif", ".tiff", ".gif", ".webp"}
QUALITY = 92  # visually lossless
METHOD = 6    # slowest/best compression


def human(size):
    for unit in ("B", "KB", "MB", "GB"):
        if size < 1024:
            return f"{size:.0f} {unit}" if unit == "B" else f"{size:.1f} {unit}"
        size /= 1024
    return f"{size:.1f} TB"


def convert(src, out_dir, quality, lossless):
    os.makedirs(out_dir, exist_ok=True)
    dst = os.path.join(out_dir, os.path.splitext(os.path.basename(src))[0] + ".webp")
    with Image.open(src) as im:
        im = ImageOps.exif_transpose(im)  # keep correct orientation
        icc = im.info.get("icc_profile")
        if im.mode not in ("RGB", "RGBA"):
            has_alpha = "transparency" in im.info or "A" in im.getbands()
            im = im.convert("RGBA" if has_alpha else "RGB")
        kwargs = dict(method=METHOD, icc_profile=icc)
        if lossless:
            kwargs["lossless"] = True
        else:
            kwargs.update(quality=quality, exact=False)
        im.save(dst, "WEBP", **kwargs)
    return dst


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("WebP Optimizer")
        self.geometry("760x520")
        self.folder = None
        self.busy = False

        top = ttk.Frame(self, padding=8)
        top.pack(fill="x")
        ttk.Button(top, text="Open Folder", command=self.open_folder).pack(side="left")
        ttk.Button(top, text="Convert All", command=self.convert_all).pack(side="left", padx=6)

        ttk.Label(top, text="Quality").pack(side="left", padx=(16, 4))
        self.quality = tk.IntVar(value=QUALITY)
        ttk.Scale(top, from_=50, to=100, variable=self.quality, length=120,
                  command=lambda v: self.quality.set(int(float(v)))).pack(side="left")
        self.q_label = ttk.Label(top, width=4, textvariable=self.quality)
        self.q_label.pack(side="left")
        self.lossless = tk.BooleanVar(value=False)
        ttk.Checkbutton(top, text="Lossless", variable=self.lossless).pack(side="left", padx=8)

        self.path_label = ttk.Label(self, text="No folder selected", padding=(8, 0))
        self.path_label.pack(fill="x")

        cols = ("name", "size", "new", "status")
        self.tree = ttk.Treeview(self, columns=cols, show="headings", selectmode="browse")
        for c, t, w in (("name", "File", 340), ("size", "Original", 90),
                        ("new", "WebP", 90), ("status", "Status", 160)):
            self.tree.heading(c, text=t)
            self.tree.column(c, width=w, anchor="w" if c == "name" else "center")
        self.tree.pack(fill="both", expand=True, padx=8, pady=8)
        self.tree.tag_configure("done", foreground="#1a7f37")
        self.tree.tag_configure("error", foreground="#cf222e")
        self.tree.bind("<<TreeviewSelect>>", self.on_click)

        self.footer = ttk.Label(self, text="", padding=8)
        self.footer.pack(fill="x")

    def open_folder(self):
        folder = filedialog.askdirectory(title="Select image folder")
        if not folder:
            return
        self.folder = folder
        self.path_label.config(text=folder)
        self.tree.delete(*self.tree.get_children())
        for name in sorted(os.listdir(folder)):
            full = os.path.join(folder, name)
            if os.path.isfile(full) and os.path.splitext(name)[1].lower() in EXTENSIONS:
                self.tree.insert("", "end", iid=name,
                                 values=(name, human(os.path.getsize(full)), "-", "Pending"))
        self.footer.config(text=f"{len(self.tree.get_children())} images found")

    def on_click(self, _event):
        sel = self.tree.selection()
        if sel:
            self.run([sel[0]])

    def convert_all(self):
        if not self.folder:
            messagebox.showinfo("WebP Optimizer", "Open a folder first.")
            return
        self.run(list(self.tree.get_children()))

    def run(self, names):
        if self.busy or not self.folder:
            return
        self.busy = True
        quality, lossless = self.quality.get(), self.lossless.get()
        threading.Thread(target=self.worker, args=(names, quality, lossless), daemon=True).start()

    def worker(self, names, quality, lossless):
        out_dir = os.path.join(self.folder, "webp")
        for name in names:
            self.after(0, self.set_row, name, None, "Converting...", None)
            try:
                dst = convert(os.path.join(self.folder, name), out_dir, quality, lossless)
                self.after(0, self.set_row, name, human(os.path.getsize(dst)), "Done", "done")
            except Exception as exc:  # noqa: BLE001
                self.after(0, self.set_row, name, None, f"Error: {exc}", "error")
        self.after(0, self.finish)

    def set_row(self, name, new_size, status, tag):
        vals = list(self.tree.item(name, "values"))
        if new_size:
            vals[2] = new_size
        vals[3] = status
        self.tree.item(name, values=vals, tags=(tag,) if tag else ())

    def finish(self):
        self.busy = False
        orig = new = 0
        for name in self.tree.get_children():
            p = os.path.join(self.folder, "webp", os.path.splitext(name)[0] + ".webp")
            if self.tree.set(name, "status") == "Done" and os.path.exists(p):
                orig += os.path.getsize(os.path.join(self.folder, name))
                new += os.path.getsize(p)
        if orig:
            self.footer.config(
                text=f"Saved to {os.path.join(self.folder, 'webp')}  |  "
                     f"{human(orig)} -> {human(new)} ({100 - new * 100 / orig:.0f}% smaller)")


if __name__ == "__main__":
    App().mainloop()
