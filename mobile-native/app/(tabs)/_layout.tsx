// Operator's Signal Room: bottom navigation prioritizes frequent operating loops, not a miniature desktop sidebar.
import { Tabs } from "expo-router";

import { AppIcon } from "@/components/app-icon";

const tabColor = "#6C5CE7";
const muted = "#9298B1";

export default function TabLayout() {
  return <Tabs screenOptions={{ headerShown: false, tabBarActiveTintColor: tabColor, tabBarInactiveTintColor: muted, tabBarStyle: { height: 64, paddingTop: 7, borderTopColor: "#E8EAF4", backgroundColor: "#FFFFFF" }, tabBarLabelStyle: { fontSize: 10, fontWeight: "700" } }}><Tabs.Screen name="index" options={{ title: "Home", tabBarIcon: ({ color }) => <AppIcon name="view-dashboard-outline" color={color} /> }} /><Tabs.Screen name="orders" options={{ title: "Orders", tabBarIcon: ({ color }) => <AppIcon name="clipboard-text-outline" color={color} /> }} /><Tabs.Screen name="notifications" options={{ title: "Signals", tabBarIcon: ({ color }) => <AppIcon name="bell-outline" color={color} /> }} /><Tabs.Screen name="account" options={{ title: "Account", tabBarIcon: ({ color }) => <AppIcon name="account-circle-outline" color={color} /> }} /></Tabs>;
}
