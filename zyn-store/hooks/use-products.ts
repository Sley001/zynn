"use client";

import { useEffect, useState } from "react";

import { PRODUCTS } from "@/data/products";
import { getProducts, isApiConfigured } from "@/lib/api";
import type { Product } from "@/types/store";

export function useProducts() {
  const [products, setProducts] = useState<Product[]>([...PRODUCTS]);
  const [isLoading, setIsLoading] = useState(isApiConfigured);
  const [isFromApi, setIsFromApi] = useState(false);
  const [hasError, setHasError] = useState(false);

  useEffect(() => {
    if (!isApiConfigured) return;

    const controller = new AbortController();

    getProducts(controller.signal)
      .then((apiProducts) => {
        setProducts(apiProducts);
        setIsFromApi(true);
        setHasError(false);
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setHasError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => controller.abort();
  }, []);

  return { products, isLoading, isFromApi, hasError, isApiConfigured };
}
