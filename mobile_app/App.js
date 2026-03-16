import React from 'react';
import { StyleSheet, Text, View, ScrollView, SafeAreaView } from 'react-native';
import { StatusBar } from 'expo-status-bar';

export default function App() {
  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="auto" />
      <View style={styles.header}>
        <Text style={styles.title}>NexaCRM Mobile</Text>
      </View>
      
      <ScrollView style={styles.content}>
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Predictive Lead Score</Text>
          <Text style={styles.cardValue}>88%</Text>
          <Text style={styles.cardSubtext}>High Priority Deal: Acme Corp</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.cardLabel}>Monthly Revenue (MRR)</Text>
          <Text style={styles.cardValue}>$250K</Text>
          <Text style={styles.cardSubtext}>+12.5% from last month</Text>
        </View>
        
        <View style={[styles.card, { borderLeftColor: '#f59e0b' }]}>
          <Text style={styles.cardLabel}>At-Risk Deals</Text>
          <Text style={styles.cardValue}>3</Text>
          <Text style={styles.cardSubtext}>AI suggests immediate follow-up</Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  header: {
    padding: 20,
    backgroundColor: '#3b82f6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    color: '#fff',
    fontSize: 20,
    fontWeight: 'bold',
  },
  content: {
    padding: 20,
  },
  card: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 12,
    marginBottom: 20,
    borderLeftWidth: 5,
    borderLeftColor: '#3b82f6',
    elevation: 3,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  cardLabel: {
    fontSize: 12,
    color: '#64748b',
    textTransform: 'uppercase',
  },
  cardValue: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#0f172a',
    marginVertical: 4,
  },
  cardSubtext: {
    fontSize: 14,
    color: '#475569',
  },
});
