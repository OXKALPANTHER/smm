// Operator's Signal Room: every native screen maintains a calm indigo canvas and safe, readable boundaries.
import type { PropsWithChildren } from "react";
import { StyleSheet, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

export function Screen({ children }: PropsWithChildren) {
  return <View style={styles.canvas}><SafeAreaView style={styles.safe}>{children}</SafeAreaView></View>;
}

const styles = StyleSheet.create({ canvas: { flex: 1, backgroundColor: "#F6F7FD" }, safe: { flex: 1 } });
