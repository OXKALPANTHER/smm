// Operator's Signal Room: the root keeps navigation light and makes role context available to every workflow.
import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";

import { RoyalAppProvider } from "@/lib/app-state";

export default function RootLayout() {
  return <RoyalAppProvider><StatusBar style="dark" /><Stack screenOptions={{ headerShown: false, animation: "fade" }}><Stack.Screen name="(tabs)" /><Stack.Screen name="role" options={{ presentation: "modal" }} /></Stack></RoyalAppProvider>;
}
